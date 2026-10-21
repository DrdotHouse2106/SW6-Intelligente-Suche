<?php declare(strict_types=1);

namespace Nova\Search\StoreApi;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * Antwort von POST /store-api/nova-search/popular: { "terms": ["chrom", "scheinwerfer", …] }
 *
 * @extends StoreApiResponse<ArrayStruct<array<string, mixed>>>
 */
class NovaPopularRouteResponse extends StoreApiResponse
{
    /**
     * @param list<string> $terms
     */
    public function __construct(array $terms)
    {
        /** @var array<string, mixed> $data */
        $data = ['terms' => $terms];

        parent::__construct(new ArrayStruct($data, 'nova_search_popular'));
    }

    /**
     * @return list<string>
     */
    public function getTerms(): array
    {
        /** @var list<string> $terms */
        $terms = $this->object->get('terms');

        return $terms;
    }
}
