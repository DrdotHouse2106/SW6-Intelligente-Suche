<?php declare(strict_types=1);

namespace Nova\Search\StoreApi;

use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Store-API: Nutzungssignale aus Apps (Suche, Klick, Warenkorb, Kauf).
 */
abstract class AbstractNovaTrackRoute
{
    abstract public function getDecorated(): AbstractNovaTrackRoute;

    abstract public function track(Request $request, SalesChannelContext $context): NoContentResponse;
}
