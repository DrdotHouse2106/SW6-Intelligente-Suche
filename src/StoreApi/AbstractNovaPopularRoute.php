<?php declare(strict_types=1);

namespace Nova\Search\StoreApi;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Store-API: beliebte Suchanfragen für Apps.
 */
abstract class AbstractNovaPopularRoute
{
    abstract public function getDecorated(): AbstractNovaPopularRoute;

    abstract public function load(Request $request, SalesChannelContext $context): NovaPopularRouteResponse;
}
