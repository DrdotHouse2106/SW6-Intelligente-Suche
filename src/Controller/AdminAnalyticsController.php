<?php declare(strict_types=1);

namespace Nova\Search\Controller;

use Nova\Search\Rewrite\ClaudeBudget;
use Nova\Search\Service\SearchAnalytics;
use Nova\Search\Service\SearchExport;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API für den Bereich „Erweiterungen → Nova Suche“: Auswertung und CSV-Export.
 * Nur für angemeldete Benutzer der Administration (keine Integrationen).
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class AdminAnalyticsController
{
    public function __construct(
        private readonly SearchAnalytics $analytics,
        private readonly SearchExport $export,
        private readonly ClaudeBudget $budget,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/api/_action/nova-search/analytics', name: 'api.action.nova_search.analytics', methods: ['GET'])]
    public function analytics(Request $request, Context $context): JsonResponse
    {
        if (!$this->isAdminUser($context)) {
            return new JsonResponse(['message' => 'Nur für Benutzer der Administration.'], 403);
        }

        try {
            $data = $this->analytics->overview($this->salesChannelId($request), $request->query->getInt('days', 30));
            $data['claude'] = [
                'month' => $this->budget->month(),
                'spentUsd' => round($this->budget->spentMicro() / 1_000_000, 4),
                'limitUsd' => round($this->budget->limitMicro() / 1_000_000, 2),
            ];

            return new JsonResponse($data);
        } catch (\Throwable $e) {
            $this->logger->error('Nova Suche: Auswertung fehlgeschlagen', ['exception' => $e]);

            return new JsonResponse(['message' => 'Auswertung fehlgeschlagen. Details stehen im Shopware-Log.'], 500);
        }
    }

    #[Route(path: '/api/_action/nova-search/export', name: 'api.action.nova_search.export', methods: ['GET'])]
    public function export(Request $request, Context $context): Response
    {
        if (!$this->isAdminUser($context)) {
            return new JsonResponse(['message' => 'Nur für Benutzer der Administration.'], 403);
        }

        $type = $request->query->getString('type');
        if (!\in_array($type, SearchExport::TYPES, true)) {
            return new JsonResponse(['message' => 'Unbekannter Export-Typ.'], 400);
        }

        $days = $request->query->getInt('days');
        $salesChannelId = $this->salesChannelId($request);
        $data = $this->export->fetch($type, $salesChannelId !== null ? [$salesChannelId] : [], $days > 0 ? $days : null);

        $response = new Response(SearchExport::toCsv($data['columns'], $data['rows']));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="nova-suche-%s-%s.csv"', $type, date('Y-m-d')));

        return $response;
    }

    private function salesChannelId(Request $request): ?string
    {
        $id = $request->query->getString('salesChannelId');

        return preg_match('/^[0-9a-f]{32}$/', $id) === 1 ? $id : null;
    }

    private function isAdminUser(Context $context): bool
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource && $source->getUserId() !== null;
    }
}
