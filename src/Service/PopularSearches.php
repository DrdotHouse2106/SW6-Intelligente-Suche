<?php declare(strict_types=1);

namespace Nova\Search\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * „Beliebte Suchanfragen“ – gemeinsam für das Suchfenster und die Store-API (App):
 * manuell gepflegte Begriffe zuerst, danach automatisch ermittelte (mit Sperrliste).
 */
class PopularSearches
{
    public const MAX = 20;

    public function __construct(
        private readonly SearchStatistics $statistics,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<string>
     */
    public function get(string $salesChannelId, int $limit = 12): array
    {
        $limit = max(1, min(self::MAX, $limit));
        $popular = self::split($this->config->getString('NovaSearch.config.popularSearches', $salesChannelId));

        if ($this->config->get('NovaSearch.config.popularAuto', $salesChannelId) !== false) {
            try {
                $popular = array_merge($popular, $this->statistics->popularTerms(
                    $salesChannelId,
                    $this->config->getInt('NovaSearch.config.popularMinSearches', $salesChannelId) ?: 5,
                    $limit,
                    self::split($this->config->getString('NovaSearch.config.popularBlocklist', $salesChannelId))
                ));
            } catch (\Throwable $e) {
                $this->logger->warning('Nova Suche: Beliebte Suchanfragen konnten nicht geladen werden', ['exception' => $e]);
            }
        }

        $unique = [];
        foreach ($popular as $term) {
            $unique[mb_strtolower($term)] ??= $term;
        }

        return \array_slice(array_values($unique), 0, $limit);
    }

    /**
     * Eine Angabe pro Zeile oder durch Komma getrennt
     *
     * @return list<string>
     */
    private static function split(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    }
}
