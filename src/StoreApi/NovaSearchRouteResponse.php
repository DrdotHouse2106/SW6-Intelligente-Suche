<?php declare(strict_types=1);

namespace Nova\Search\StoreApi;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * Antwort von POST /store-api/nova-search/search
 *
 * {
 *   "ids": ["…"], "total": 30, "page": 1, "limit": 24,
 *   "sorting": "score", "availableSortings": [{ "key": "score", "label": "Relevanz" }],
 *   "rewrite": null | { "original": "bremsbelag ente", "term": "bremsbelag 2cv" }
 * }
 *
 * @extends StoreApiResponse<ArrayStruct<array<string, mixed>>>
 */
class NovaSearchRouteResponse extends StoreApiResponse
{
    /**
     * @param list<string> $ids
     * @param list<array{key: string, label: string}> $availableSortings
     * @param array{original: string, term: string}|null $rewrite
     */
    public function __construct(array $ids, int $total, int $page, int $limit, ?string $sorting, array $availableSortings, ?array $rewrite)
    {
        /** @var array<string, mixed> $data */
        $data = [
            'ids' => $ids,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'sorting' => $sorting,
            'availableSortings' => $availableSortings,
            'rewrite' => $rewrite,
        ];

        parent::__construct(new ArrayStruct($data, 'nova_search_result'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getResult(): array
    {
        return $this->object->all();
    }
}
