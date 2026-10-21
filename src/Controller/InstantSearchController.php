<?php declare(strict_types=1);

namespace Nova\Search\Controller;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\HttpException;
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
 * Liefert das Ergebnisfenster der Sofortsuche als HTML-Fragment.
 *
 * Nutzt Shopwares eigene Produktsuche (MySQL oder OpenSearch, je nach Installation),
 * berechnet aber nur die Hersteller-Facette – Eigenschafts-, Preis- und Bewertungs-
 * Aggregationen werden abgeschaltet, damit die Antwort auch ohne OpenSearch schnell ist.
 * Die Antwort ist per HTTP-Cache cachebar.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class InstantSearchController extends StorefrontController
{
    private const MIN_TERM_LENGTH = 2;
    private const MAX_TERM_LENGTH = 100;

    /**
     * @param SalesChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly AbstractProductSearchRoute $productSearchRoute,
        private readonly SalesChannelRepository $categoryRepository,
        private readonly SystemConfigService $config,
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
        $salesChannelId = $context->getSalesChannelId();
        $term = mb_substr(trim((string) $request->query->get('search', '')), 0, self::MAX_TERM_LENGTH);
        // Nur gültige IDs durchlassen (32 Hex-Zeichen, mit „|“ getrennt)
        $manufacturerIds = array_values(array_filter(
            explode('|', (string) $request->query->get('manufacturer', '')),
            static fn (string $id): bool => preg_match('/^[0-9a-f]{32}$/', $id) === 1
        ));

        $products = new ProductCollection();
        $manufacturers = null;
        $total = 0;
        $categories = new CategoryCollection();

        if (mb_strlen($term) >= self::MIN_TERM_LENGTH) {
            $limit = max(1, min(48, $this->config->getInt('NovaSearch.config.productLimit', $salesChannelId) ?: 12));

            // Suchparameter in der URL (query), Filter-Schalter im Request-Body (request) –
            // so liest Shopware sie aus. Nur die Hersteller-Facette wird berechnet.
            $searchRequest = $request->duplicate(
                [
                    'search' => $term,
                    'limit' => $limit,
                    'p' => 1,
                    'manufacturer' => implode('|', $manufacturerIds),
                ],
                [
                    'manufacturer-filter' => $this->config->get('NovaSearch.config.showManufacturers', $salesChannelId) !== false,
                    'property-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => false,
                    'shipping-free-filter' => false,
                ]
            );

            try {
                $criteria = new Criteria();
                $criteria->setTitle('nova-search::instant');

                $result = $this->productSearchRoute->load($searchRequest, $context, $criteria)->getListingResult();

                $products = $result->getEntities();
                $total = $result->getTotal();

                $aggregation = $result->getAggregations()->get('manufacturer');
                if ($aggregation instanceof EntityResult) {
                    $manufacturers = $aggregation->getEntities();
                }
            } catch (HttpException) {
                // z. B. Suchbegriff besteht nur aus Stoppwörtern → leeres Ergebnis anzeigen
            }

            if ($this->config->get('NovaSearch.config.showCategories', $salesChannelId) !== false) {
                $categories = $this->findCategories($term, $context);
            }
        }

        $response = $this->renderStorefront('@NovaSearch/storefront/nova-search/results.html.twig', [
            'term' => $term,
            'products' => $products,
            'total' => $total,
            'manufacturers' => $manufacturers,
            'activeManufacturers' => $manufacturerIds,
            'categories' => $categories,
        ]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
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
