<?php declare(strict_types=1);

namespace Nova\Search\StoreApi;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Store-API: Produktsuche für Apps (IDs in Ranking-Reihenfolge). Kann wie jede Shopware-Route dekoriert werden.
 */
abstract class AbstractNovaSearchRoute
{
    abstract public function getDecorated(): AbstractNovaSearchRoute;

    abstract public function search(Request $request, SalesChannelContext $context): NovaSearchRouteResponse;
}
