<?php declare(strict_types=1);

namespace Nova\Search\Search;

use Nova\Search\Rules\SearchRules;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;

/**
 * Synonyme: Enthält der Suchbegriff ein Wort aus einer Synonym-Gruppe, werden die Suchwörter der
 * anderen Begriffe der Gruppe gleichwertig ergänzt („bremsklötze“ findet auch „Bremsbeläge“).
 *
 * Bei Shopwares UND-Suche gilt die Ergänzung nur für das betroffene Wort – die übrigen Wörter
 * müssen weiterhin passen („bremsklötze 2cv“ → Bremsbeläge für den 2CV, nicht alle Bremsbeläge).
 */
class SynonymTermInterpreter implements ProductSearchTermInterpreterInterface
{
    private const MAX_TERMS = 40;

    public function __construct(
        private readonly ProductSearchTermInterpreterInterface $decorated,
        private readonly SearchRules $rules,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function interpret(string $word, Context $context): SearchPattern
    {
        $pattern = $this->decorated->interpret($word, $context);

        try {
            $source = $context->getSource();
            $salesChannelId = $source instanceof SalesChannelApiSource ? $source->getSalesChannelId() : null;
            $expansions = $this->rules->expansions($word, $salesChannelId);
        } catch (\Throwable $e) {
            $this->logger->warning('Nova Suche: Synonyme konnten nicht gelesen werden', ['exception' => $e]);

            return $pattern;
        }

        if ($expansions === []) {
            return $pattern;
        }

        return $this->expand($pattern, $expansions, $context);
    }

    /**
     * @param list<array{phrase: string, alternatives: list<string>}> $expansions
     */
    private function expand(SearchPattern $pattern, array $expansions, Context $context): SearchPattern
    {
        $tokenTerms = $pattern->getTokenTerms();

        /** @var array<string, float> $scores */
        $scores = [];
        foreach ($pattern->getTerms() as $term) {
            $scores[$term->getTerm()] = max($scores[$term->getTerm()] ?? 0.0, $term->getScore());
        }
        $topScore = $scores === [] ? 1.0 : max($scores);

        foreach ($expansions as $expansion) {
            $phraseKeywords = self::flatten($this->decorated->interpret($expansion['phrase'], $context)->getTokenTerms());

            $alternativeKeywords = [];
            foreach ($expansion['alternatives'] as $alternative) {
                $alternativePattern = $this->decorated->interpret($alternative, $context);
                $alternativeKeywords = array_merge($alternativeKeywords, self::flatten($alternativePattern->getTokenTerms()));

                foreach ($alternativePattern->getTerms() as $term) {
                    // Synonyme zählen wie das Original, aber nie mehr als der beste Original-Treffer
                    $scores[$term->getTerm()] = max($scores[$term->getTerm()] ?? 0.0, min($term->getScore(), $topScore));
                }
            }

            $alternativeKeywords = array_values(array_unique($alternativeKeywords));
            if ($alternativeKeywords === []) {
                continue;
            }

            foreach ($tokenTerms as $index => $keywords) {
                // Wortgruppe des Synonyms: teilt Suchwörter mit dem Synonym – oder beide haben keine
                // Treffer im Suchwort-Verzeichnis (typischer Fall: Kunde nutzt ein anderes Wort als der Shop)
                $belongs = $keywords === []
                    ? $phraseKeywords === []
                    : array_intersect($keywords, $phraseKeywords) !== [];

                if ($belongs) {
                    $tokenTerms[$index] = array_values(array_unique(array_merge($keywords, $alternativeKeywords)));
                }
            }
        }

        arsort($scores);
        $scores = \array_slice($scores, 0, self::MAX_TERMS, true);

        $expanded = new SearchPattern($pattern->getOriginal());
        $expanded->setBooleanClause($pattern->getBooleanClause() === SearchPattern::BOOLEAN_CLAUSE_AND);
        $expanded->setTokenTerms($tokenTerms);
        foreach ($scores as $keyword => $score) {
            $expanded->addTerm(new SearchTerm((string) $keyword, $score));
        }

        return $expanded;
    }

    /**
     * @param array<int, list<string>> $tokenTerms
     *
     * @return list<string>
     */
    private static function flatten(array $tokenTerms): array
    {
        $keywords = [];
        foreach ($tokenTerms as $terms) {
            foreach ($terms as $term) {
                $keywords[] = $term;
            }
        }

        return array_values(array_unique($keywords));
    }
}
