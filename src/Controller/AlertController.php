<?php

namespace App\Controller;

use App\Service\AlertService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/alerts')]
#[OA\Tag(name: 'Alerts')]
final class AlertController extends AbstractController
{
    public function __construct(
        private AlertService $alertService
    ) {
    }

    #[Route('/active', name: 'alerts_active', methods: ['GET'])]
    #[OA\Get(
        path: '/api/alerts/active',
        summary: 'Get active alerts',
        tags: ['Alerts'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of active pollution alerts',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'active_alerts', type: 'array', items: new OA\Items()),
                        new OA\Property(property: 'count', type: 'integer'),
                        new OA\Property(property: 'timestamp', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function active(): JsonResponse
    {
        $alerts = $this->alertService->getActiveAlerts();

        $data = array_map(function($alert) {
            return [
                'id' => $alert->getId(),
                'pollutant' => $alert->getPollutant(),
                'value' => $alert->getValue(),
                'level' => $alert->getLevel(),
                'message' => $alert->getMessage(),
                'latitude' => $alert->getLatitude(),
                'longitude' => $alert->getLongitude(),
                'created_at' => $alert->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $alerts);

        return $this->json([
            'active_alerts' => $data,
            'count' => count($data),
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get alert history
     */
    #[Route('/history', name: 'alerts_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date') ? new \DateTime($request->query->get('start_date')) : null;
        $endDate = $request->query->get('end_date') ? new \DateTime($request->query->get('end_date')) : null;
        $limit = (int)$request->query->get('limit', 50);

        $alerts = $this->alertService->getAlertHistory($startDate, $endDate, $limit);

        $data = array_map(function($alert) {
            return [
                'id' => $alert->getId(),
                'pollutant' => $alert->getPollutant(),
                'value' => $alert->getValue(),
                'level' => $alert->getLevel(),
                'message' => $alert->getMessage(),
                'is_active' => $alert->isActive(),
                'created_at' => $alert->getCreatedAt()->format('Y-m-d H:i:s'),
                'resolved_at' => $alert->getResolvedAt()?->format('Y-m-d H:i:s'),
                'latitude' => $alert->getLatitude(),
                'longitude' => $alert->getLongitude()
            ];
        }, $alerts);

        return $this->json([
            'alerts' => $data,
            'count' => count($data),
            'filters' => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Simulate an alert (for testing)
     */
    #[Route('/simulate', name: 'alerts_simulate', methods: ['POST'])]
    public function simulate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $pollutant = $data['pollutant'] ?? 'SO2';
        $value = $data['value'] ?? 150;

        try {
            $alert = $this->alertService->simulateAlert($pollutant, $value);

            return $this->json([
                'message' => 'Alert simulated successfully',
                'alert' => [
                    'id' => $alert->getId(),
                    'pollutant' => $alert->getPollutant(),
                    'value' => $alert->getValue(),
                    'level' => $alert->getLevel(),
                    'message' => $alert->getMessage(),
                    'created_at' => $alert->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Simulation failed',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get alert statistics
     */
    #[Route('/stats', name: 'alerts_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $statistics = $this->alertService->getAlertStatistics();

        return $this->json($statistics);
    }

    /**
     * Get all alerts (admin)
     */
    #[Route('', name: 'alerts_all', methods: ['GET'])]
    public function all(Request $request): JsonResponse
    {
        $limit = (int)$request->query->get('limit', 100);

        return $this->history($request);
    }
}
