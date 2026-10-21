<?php declare(strict_types=1);

namespace Nova\Search\StoreApi;

use Nova\Search\Service\PopularSearches;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /store-api/nova-search/popular – dieselben „Beliebten Suchanfragen“ wie im Suchfenster
 * (manuelle Liste zuerst, danach automatisch ermittelte, Sperrliste beachtet).
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class NovaPopularRoute extends AbstractNovaPopularRoute
{
    public const DEFAULT_LIMIT = 10;

    public function __construct(private readonly PopularSearches $popularSearches)
    {
    }

    public function getDecorated(): AbstractNovaPopularRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/nova-search/popular', name: 'store-api.nova-search.popular', methods: [Request::METHOD_POST])]
    public function load(Request $request, SalesChannelContext $context): NovaPopularRouteResponse
    {
        $value = $request->request->all()['limit'] ?? null;
        $limit = \is_numeric($value) && (int) $value > 0 ? min(PopularSearches::MAX, (int) $value) : self::DEFAULT_LIMIT;

        return new NovaPopularRouteResponse($this->popularSearches->get($context->getSalesChannelId(), $limit));
    }
}
