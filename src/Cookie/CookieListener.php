<?php declare(strict_types=1);

namespace Nova\Search\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Einträge im Shopware-Cookie-Dialog:
 *  - Statistik:          „nova-search-learning“ – Zuordnung von Käufen zu Suchbegriffen
 *  - Komfortfunktionen:  „nova-recently-viewed“ – Liste „Zuletzt angesehen“ im Browser
 */
class CookieListener
{
    public const LEARNING = 'nova-search-learning';
    public const RECENTLY_VIEWED = 'nova-recently-viewed';

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $groups = $event->cookieGroupCollection;

        if ($this->config->get('NovaSearch.config.learningActive', $salesChannelId) !== false) {
            $this->add($groups, CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL, self::LEARNING, 'novaSearch.cookie.learning');
        }

        if ($this->config->get('NovaSearch.config.recentlyViewed', $salesChannelId) !== false
            && $this->config->get('NovaSearch.config.recentlyViewedConsent', $salesChannelId) !== false) {
            $this->add($groups, CookieProvider::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES, self::RECENTLY_VIEWED, 'novaSearch.cookie.recentlyViewed');
        }
    }

    private function add(CookieGroupCollection $groups, string $groupName, string $cookie, string $label): void
    {
        $group = $groups->get($groupName);
        if ($group === null) {
            return;
        }

        $entries = $group->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $group->setEntries($entries);
        }

        $entry = new CookieEntry($cookie);
        $entry->name = $label;
        $entry->value = '1';
        $entry->expiration = 30;
        $entries->add($entry);
    }
}
