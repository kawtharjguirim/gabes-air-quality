<?php

namespace App\Controller;

use App\Service\PredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/predictions')]
final class PredictionController extends AbstractController
{
    public function __construct(
        private PredictionService $predictionService
    ) {
    }

    /**
     * Get predictions for next N hours
     */
    #[Route('/next-6h', name: 'predictions_next_hours', methods: ['GET'])]
    public function nextHours(Request $request): JsonResponse
    {
        $hours = (int)$request->query->get('hours', 6);
        $predictions = $this->predictionService->getNextHoursPredictions($hours);

        // Group by pollutant for easier frontend consumption
        $grouped = [];
        foreach ($predictions as $pred) {
            $pollutant = $pred->getPollutant();
            if (!isset($grouped[$pollutant])) {
                $grouped[$pollutant] = [];
            }
            $grouped[$pollutant][] = [
                'hours_ahead' => $pred->getHoursAhead(),
                'value' => $pred->getPredictedValue(),
                'prediction_for' => $pred->getPredictionFor()->format('Y-m-d H:i:s'),
                'created_at' => $pred->getCreatedAt()->format('Y-m-d H:i:s'),
                'model_version' => $pred->getModelVersion()
            ];
        }

        return $this->json([
            'hours' => $hours,
            'predictions' => $grouped,
            'total_predictions' => count($predictions),
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Generate new predictions
     */
    #[Route('/generate', name: 'predictions_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $hoursAhead = $data['hours'] ?? 6;
        $modelVersion = $data['model_version'] ?? 'v1.0';

        try {
            $predictions = $this->predictionService->generatePredictions($hoursAhead, $modelVersion);

            return $this->json([
                'message' => 'Predictions generated successfully',
                'count' => count($predictions),
                'hours_ahead' => $hoursAhead,
                'model_version' => $modelVersion
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to generate predictions',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get prediction accuracy metrics
     */
    #[Route('/accuracy', name: 'predictions_accuracy', methods: ['GET'])]
    public function accuracy(Request $request): JsonResponse
    {
        $pollutant = $request->query->get('pollutant');
        $hoursAhead = $request->query->get('hours_ahead') ? (int)$request->query->get('hours_ahead') : null;

        $metrics = $this->predictionService->calculateAccuracy($pollutant, $hoursAhead);

        return $this->json($metrics);
    }

    /**
     * Get prediction vs actual comparison
     */
    #[Route('/comparison/{pollutant}', name: 'predictions_comparison', methods: ['GET'])]
    public function comparison(string $pollutant, Request $request): JsonResponse
    {
        $limit = (int)$request->query->get('limit', 100);

        $data = $this->predictionService->getPredictionComparison($pollutant, $limit);

        return $this->json([
            'pollutant' => $pollutant,
            'data' => $data,
            'count' => count($data)
        ]);
    }

    /**
     * Update actual values for predictions (for evaluation)
     */
    #[Route('/update-actual', name: 'predictions_update_actual', methods: ['POST'])]
    public function updateActual(): JsonResponse
    {
        $updated = $this->predictionService->updateActualValues();

        return $this->json([
            'message' => 'Actual values updated',
            'updated_count' => $updated
        ]);
    }
}
