<?php declare(strict_types=1);

namespace Nova\Search\Subscriber;

use Nova\Search\Service\NumberIndex;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hält den Nummern-Index aktuell, wenn Produkte, deren Übersetzungen (Zusatzfelder) oder
 * Eigenschaften gespeichert bzw. gelöscht werden.
 */
class NumberIndexSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NumberIndex $index,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [EntityWrittenContainerEvent::class => 'onWritten'];
    }

    public function onWritten(EntityWrittenContainerEvent $event): void
    {
        if ($event->getContext()->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        $ids = [];
        foreach (['product', 'product_translation', 'product_property'] as $entity) {
            foreach ($event->getPrimaryKeys($entity) as $key) {
                $id = self::productId($key);
                if ($id !== null) {
                    $ids[$id] = true;
                }
            }
        }

        if ($ids === []) {
            return;
        }

        try {
            $this->index->rebuild(array_keys($ids));
        } catch (\Throwable $e) {
            // Speichern von Produkten darf nie an der Suche scheitern – der stündliche Lauf holt es nach
            $this->logger->warning('Nova Suche: Nummern-Index konnte nicht aktualisiert werden', ['exception' => $e]);
        }
    }

    /**
     * Produkt: Primärschlüssel ist die ID; Übersetzung/Eigenschaft: Array mit „productId“.
     */
    private static function productId(mixed $key): ?string
    {
        if (\is_array($key)) {
            $key = $key['productId'] ?? null;
        }

        return \is_string($key) && preg_match('/^[0-9a-f]{32}$/', $key) === 1 ? $key : null;
    }
}
