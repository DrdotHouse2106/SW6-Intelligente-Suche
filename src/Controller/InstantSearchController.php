<?php declare(strict_types=1);

namespace Nova\Search\Controller;

use Nova\Search\Rules\SearchRules;
use Nova\Search\Search\LearningProductSearchBuilder;
use Nova\Search\Subscriber\SearchRedirectSubscriber;
use Nova\Search\Service\PopularSearches;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liefert die Inhalte des Suchfensters als HTML-Fragmente:
 *  - ohne Suchbegriff: beliebte Suchanfragen, zuletzt angesehene und Highlight-Produkte
 *  - mit Suchbegriff:  Filter (Hersteller mit Anzahl, Preis), Sortierung, Kategorien, Produktraster
 *  - ab Seite 2:       nur weitere Produktkarten (Nachladen beim Scrollen)
 *
 * Nutzt Shopwares eigene Produktsuche (MySQL oder OpenSearch) – inklusive gespeicherter Umschreibungen
 * und gelernter Platzierungen (siehe LearningProductSearchBuilder). Eigenschafts-, Bewertungs- und
 * Versandfilter werden abgeschaltet, damit die Antwort auch ohne OpenSearch schnell ist.
 * Antworten sind per HTTP-Cache cachebar – außer bei 0 Treffern, damit eine später gelernte
 * Umschreibung sofort greift.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class InstantSearchController extends StorefrontController
{
    private const MIN_TERM_LENGTH = 2;
    private const MAX_TERM_LENGTH = 100;
    private const MANUFACTURER_COUNT = 'nova-manufacturer-count';

    /**
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly AbstractProductSearchRoute $productSearchRoute,
        private readonly SalesChannelRepository $categoryRepository,
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $config,
        private readonly PopularSearches $popularSearches,
        private readonly SearchRules $rules,
    ) {
    }

    #[Route(
        path: '/nova-search',
        name: 'frontend.nova-search.instant',
        defaults: ['XmlHttpRequest' => true, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET]
    )]
    public function instant(Request $request, SalesChannelContext $context): Response
    {
        $term = mb_substr(trim((string) $request->query->get('search', '')), 0, self::MAX_TERM_LENGTH);

        $response = mb_strlen($term) < self::MIN_TERM_LENGTH
            ? $this->renderStart($request, $context)
            : $this->renderResults($term, $request, $context);

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    private function renderStart(Request $request, SalesChannelContext $context): Response
    {
        $salesChannelId = $context->getSalesChannelId();

        $highlights = new ProductCollection();
        $ids = $this->config->get('NovaSearch.config.highlightProducts', $salesChannelId);
        if (\is_array($ids) && $ids !== []) {
            $highlights = $this->loadProducts(array_values(array_filter($ids, 'is_string')), $context);
        }

        // „Zuletzt angesehen“ – die IDs kommen aus dem localStorage des Besuchers (neueste zuerst)
        $recent = new ProductCollection();
        if ($this->config->get('NovaSearch.config.recentlyViewed', $salesChannelId) !== false) {
            $recentIds = \array_slice(array_values(array_filter(
                explode('|', (string) $request->query->get('recent', '')),
                static fn (string $id): bool => preg_match('/^[0-9a-f]{32}$/', $id) === 1
            )), 0, 12);
            if ($recentIds !== []) {
                $recent = $this->loadProducts($recentIds, $context);
            }
        }

        return $this->renderStorefront('@NovaSearch/storefront/nova-search/start.html.twig', [
            'popular' => $this->popularSearches->get($salesChannelId, 12),
            'highlights' => $highlights,
            'recent' => $recent,
        ]);
    }

    /**
     * Produkte in der übergebenen Reihenfolge laden (nur sichtbare, aktive Produkte).
     *
     * @param list<string> $ids
     */
    private function loadProducts(array $ids, SalesChannelContext $context): ProductCollection
    {
        $criteria = new Criteria($ids);
        $criteria->setTitle('nova-search::products');
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL));

        $products = $this->productRepository->search($criteria, $context)->getEntities();

        $sorted = new ProductCollection();
        foreach ($ids as $id) {
            $product = $products->get($id);
            if ($product !== null) {
                $sorted->add($product);
            }
        }

        return $sorted;
    }

    private function renderResults(string $term, Request $request, SalesChannelContext $context): Response
    {
        $salesChannelId = $context->getSalesChannelId();
        $page = max(1, min(50, (int) $request->query->get('p', 1)));
        $limit = max(4, min(48, $this->config->getInt('NovaSearch.config.productLimit', $salesChannelId) ?: 24));

        // Nur gültige Werte durchlassen
        $manufacturerIds = array_values(array_filter(
            explode('|', (string) $request->query->get('manufacturer', '')),
            static fn (string $id): bool => preg_match('/^[0-9a-f]{32}$/', $id) === 1
        ));
        $order = (string) $request->query->get('order', '');
        $order = preg_match('/^[a-z0-9-]{1,50}$/', $order) === 1 ? $order : '';
        $minPrice = $this->price($request->query->get('min-price'));
        $maxPrice = $this->price($request->query->get('max-price'));

        // Suchparameter in der URL (query), Filter-Schalter im Request-Body (request) – so liest Shopware sie aus
        $searchRequest = $request->duplicate(
            array_filter([
                'search' => $term,
                'limit' => $limit,
                'p' => $page,
                'manufacturer' => implode('|', $manufacturerIds),
                'order' => $order,
                'min-price' => $minPrice,
                'max-price' => $maxPrice,
            ], static fn ($v) => $v !== '' && $v !== null),
            [
                'manufacturer-filter' => true,
                'price-filter' => true,
                'property-filter' => false,
                'rating-filter' => false,
                'shipping-free-filter' => false,
                // Beim Nachladen werden keine Filterwerte gebraucht
                'no-aggregations' => $page > 1,
            ]
        );

        $criteria = new Criteria();
        $criteria->setTitle('nova-search::instant');
        if ($page === 1) {
            $criteria->addAggregation(new TermsAggregation(self::MANUFACTURER_COUNT, 'product.manufacturerId'));
        }

        try {
            $result = $this->productSearchRoute->load($searchRequest, $context, $criteria)->getListingResult();
        } catch (HttpException) {
            // z. B. Suchbegriff besteht nur aus Stoppwörtern → leeres Ergebnis
            $result = null;
        }

        $total = $result?->getTotal() ?? 0;

        // 0 Treffer nicht cachen – sonst greift eine später gelernte Umschreibung erst nach Ablauf des Caches
        if ($total === 0) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);
            $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
        }

        // Wurde mit einer gespeicherten Umschreibung gesucht? → Hinweis „Ergebnisse für …“
        $rewrite = $result?->getCriteria()->getExtension(LearningProductSearchBuilder::EXTENSION);

        $redirect = $this->rules->redirect($term, $salesChannelId);

        $variables = [
            'term' => $term,
            'redirect' => $redirect !== null ? SearchRedirectSubscriber::absolute($redirect, $request) : null,
            'rewrite' => $rewrite instanceof ArrayStruct ? $rewrite->all() : null,
            'products' => $result?->getEntities() ?? new ProductCollection(),
            'total' => $total,
            'page' => $page,
            'hasMore' => $page * $limit < $total,
        ];

        if ($page > 1) {
            return $this->renderStorefront('@NovaSearch/storefront/nova-search/items.html.twig', $variables);
        }

        // Hersteller mit Anzahl der Treffer
        $manufacturers = [];
        $counts = [];
        $countResult = $result?->getAggregations()->get(self::MANUFACTURER_COUNT);
        if ($countResult instanceof TermsResult) {
            foreach ($countResult->getBuckets() as $bucket) {
                $counts[(string) $bucket->getKey()] = $bucket->getCount();
            }
        }
        $entityResult = $result?->getAggregations()->get('manufacturer');
        if ($entityResult instanceof EntityResult) {
            foreach ($entityResult->getEntities() as $manufacturer) {
                if (!$manufacturer instanceof ProductManufacturerEntity) {
                    continue;
                }

                $manufacturers[] = [
                    'id' => $manufacturer->getId(),
                    'name' => (string) $manufacturer->getTranslation('name'),
                    'count' => $counts[$manufacturer->getId()] ?? 0,
                    'active' => \in_array($manufacturer->getId(), $manufacturerIds, true),
                ];
            }
            usort($manufacturers, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcasecmp($a['name'], $b['name']));
        }

        // Preisspanne aller Treffer (für den Schieberegler)
        $priceRange = null;
        $stats = $result?->getAggregations()->get('price');
        if ($stats instanceof StatsResult && $stats->getMax() !== null && $stats->getMax() > 0) {
            $priceRange = [
                'min' => (int) floor((float) $stats->getMin()),
                'max' => (int) ceil((float) $stats->getMax()),
                'from' => $minPrice,
                'to' => $maxPrice,
            ];
        }

        return $this->renderStorefront('@NovaSearch/storefront/nova-search/results.html.twig', $variables + [
            'manufacturers' => $this->config->get('NovaSearch.config.showManufacturers', $salesChannelId) !== false ? $manufacturers : [],
            'priceRange' => $priceRange,
            'sortings' => $result?->getAvailableSortings(),
            'currentSorting' => $result?->getSorting(),
            'categories' => $this->config->get('NovaSearch.config.showCategories', $salesChannelId) !== false
                ? $this->findCategories($term, $context)
                : new CategoryCollection(),
            'filterCount' => \count($manufacturerIds) + ($minPrice !== null || $maxPrice !== null ? 1 : 0),
        ]);
    }

    private function price(mixed $value): ?int
    {
        return is_numeric($value) && (float) $value >= 0 ? (int) round((float) $value) : null;
    }

    private function findCategories(string $term, SalesChannelContext $context): CategoryCollection
    {
        $rootId = $context->getSalesChannel()->getNavigationCategoryId();

        $criteria = new Criteria();
        $criteria->setTitle('nova-search::categories');
        $criteria->setLimit(6);
        $criteria->addFilter(
            new ContainsFilter('name', $term),
            new ContainsFilter('path', '|' . $rootId . '|'),
            new EqualsFilter('active', true),
            new EqualsFilter('visible', true),
        );
        $criteria->addSorting(new FieldSorting('level'), new FieldSorting('name'));

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }
}
