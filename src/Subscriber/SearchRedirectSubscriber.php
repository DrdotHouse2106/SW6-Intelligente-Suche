<?php declare(strict_types=1);

namespace Nova\Search\Subscriber;

use Nova\Search\Rules\SearchRules;
use Psr\Log\LoggerInterface;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Weiterleitungen: Ist für den Suchbegriff ein Ziel hinterlegt („kundenkonto = /account“),
 * führt die Suchergebnisseite direkt dorthin – auch per Enter aus dem Suchfenster.
 */
class SearchRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SearchRules $rules,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Nach dem Router (32), damit die Route bekannt ist
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->attributes->get('_route') !== 'frontend.search.page') {
            return;
        }

        $term = $request->query->get('search');
        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if (!\is_string($term) || trim($term) === '' || !\is_string($salesChannelId)) {
            return;
        }

        try {
            $target = $this->rules->redirect($term, $salesChannelId);
        } catch (\Throwable $e) {
            $this->logger->warning('Nova Suche: Weiterleitungen konnten nicht gelesen werden', ['exception' => $e]);

            return;
        }

        if ($target !== null) {
            $event->setResponse(new RedirectResponse(self::absolute($target, $request)));
        }
    }

    /**
     * Pfade im Shop beziehen sich auf die Adresse des Verkaufskanals (inkl. Sprachpfad wie /en).
     */
    public static function absolute(string $target, Request $request): string
    {
        if (!str_starts_with($target, '/')) {
            return $target;
        }

        $base = $request->attributes->get(RequestTransformer::STOREFRONT_URL);

        return (\is_string($base) ? rtrim($base, '/') : '') . $target;
    }
}
