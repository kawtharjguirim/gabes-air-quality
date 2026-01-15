<?php

namespace App\Controller;

use App\Service\AlertService;
use App\Service\PredictionService;
use App\Repository\ModelMetricRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_USER')] // Change to ROLE_ADMIN in production
final class AdminDashboardController extends AbstractController
{
    public function __construct(
        private AlertService $alertService,
        private PredictionService $predictionService,
        private ModelMetricRepository $modelMetricRepository
    ) {
    }

    /**
     * Get admin dashboard statistics
     */
    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $alertStats = $this->alertService->getAlertStatistics();
        
        // Get latest model metrics
        $latestMetric = $this->modelMetricRepository->findOneBy([], ['trainedAt' => 'DESC']);

        // Get accuracy for all pollutants
        $so2Accuracy = $this->predictionService->calculateAccuracy('SO2');
        $nh3Accuracy = $this->predictionService->calculateAccuracy('NH3');
        $pm25Accuracy = $this->predictionService->calculateAccuracy('PM2.5');

        return $this->json([
            'alerts' => $alertStats,
            'latest_model' => $latestMetric ? [
                'name' => $latestMetric->getModelName(),
                'rmse' => $latestMetric->getRmse(),
                'mae' => $latestMetric->getMae(),
                'r2' => $latestMetric->getR2(),
                'trained_at' => $latestMetric->getTrainedAt()->format('Y-m-d H:i:s')
            ] : null,
            'prediction_accuracy' => [
                'so2' => $so2Accuracy,
                'nh3' => $nh3Accuracy,
                'pm25' => $pm25Accuracy
            ],
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get model performance comparison
     */
    #[Route('/model/performance', name: 'admin_model_performance', methods: ['GET'])]
    public function modelPerformance(): JsonResponse
    {
        $metrics = $this->modelMetricRepository->findBy([], ['trainedAt' => 'DESC'], 10);

        $data = array_map(function($metric) {
            return [
                'model_name' => $metric->getModelName(),
                'rmse' => $metric->getRmse(),
                'mae' => $metric->getMae(),
                'r2' => $metric->getR2(),
                'trained_at' => $metric->getTrainedAt()->format('Y-m-d H:i:s')
            ];
        }, $metrics);

        return $this->json([
            'models' => $data,
            'count' => count($data)
        ]);
    }

    /**
     * Get feature importance (mock - to be replaced with real ML data)
     */
    #[Route('/model/features', name: 'admin_model_features', methods: ['GET'])]
    public function modelFeatures(): JsonResponse
    {
        // This would typically come from your ML model
        $features = [
            ['name' => 'SO₂ (t-1h)', 'importance' => 0.35],
            ['name' => 'Temperature', 'importance' => 0.22],
            ['name' => 'Wind Speed', 'importance' => 0.18],
            ['name' => 'Humidity', 'importance' => 0.12],
            ['name' => 'Hour of Day', 'importance' => 0.08],
            ['name' => 'Wind Direction', 'importance' => 0.05]
        ];

        return $this->json([
            'features' => $features,
            'note' => 'Feature importance from latest trained model'
        ]);
    }
}
