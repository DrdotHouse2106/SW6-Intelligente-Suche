<?php declare(strict_types=1);

namespace Nova\Search\Subscriber;

use Nova\Search\Service\SearchStatistics;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ordnet Käufe dem Suchbegriff zu, über den das Produkt gefunden wurde.
 *
 * Der Browser merkt sich (nur mit Zustimmung zur Kategorie „Statistik“) im Cookie „nova-search-attr“
 * kurzzeitig, welches Produkt über welchen Suchbegriff angeklickt wurde. Beim Abschluss der Bestellung
 * wird das ausgewertet und das Cookie gelöscht. Gespeichert wird nur „Begriff × Produkt: +1 Kauf“ –
 * kein Bezug zur Bestellung oder zum Kunden.
 */
class OrderAttributionSubscriber implements EventSubscriberInterface
{
    public const COOKIE = 'nova-search-attr';

    private bool $clearCookie = false;

    public function __construct(
        private readonly SearchStatistics $statistics,
        private readonly RequestStack $requestStack,
        private readonly SystemConfigService $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        $raw = $request?->cookies->get(self::COOKIE);
        if (!\is_string($raw) || $raw === '' || \strlen($raw) > 4096) {
            return;
        }

        $this->clearCookie = true;
        $salesChannelId = $event->getSalesChannelId();
        if ($this->config->get('NovaSearch.config.learningActive', $salesChannelId) === false) {
            return;
        }

        $attribution = json_decode($raw, true);
        if (!\is_array($attribution)) {
            return;
        }

        try {
            foreach ($event->getOrder()->getLineItems() ?? [] as $lineItem) {
                if ($lineItem->getType() !== 'product') {
                    continue;
                }

                foreach (array_filter([$lineItem->getProductId(), $lineItem->getReferencedId()]) as $productId) {
                    $term = $attribution[$productId] ?? null;
                    if (\is_string($term) && Uuid::isValid($productId)) {
                        $this->statistics->recordProduct($salesChannelId, $term, $productId, SearchStatistics::TYPE_ORDER);
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Nova Suche: Kauf konnte nicht zugeordnet werden', ['exception' => $e]);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$this->clearCookie || !$event->isMainRequest()) {
            return;
        }

        $this->clearCookie = false;
        $event->getResponse()->headers->setCookie(Cookie::create(self::COOKIE, null, 1, '/'));
    }
}
