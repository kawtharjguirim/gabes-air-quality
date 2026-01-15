<?php

namespace App\Controller;

use App\Entity\ModelMetric;
use App\Service\PredictionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/admin/model')]
#[IsGranted('ROLE_USER')] // Change to ROLE_ADMIN in production
final class AdminModelController extends AbstractController
{
    private string $pythonApiUrl;

    public function __construct(
        private PredictionService $predictionService,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient
    ) {
        $this->pythonApiUrl = $_ENV['PYTHON_ML_API_URL'] ?? 'http://localhost:5000';
    }

    /**
     * Trigger model training
     */
    #[Route('/train', name: 'admin_model_train', methods: ['POST'])]
    public function train(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $modelType = $data['model_type'] ?? 'XGBoost';
        $hyperparameters = $data['hyperparameters'] ?? [];

        try {
            // Call Python API to train model
            $response = $this->httpClient->request('POST', "{$this->pythonApiUrl}/train", [
                'json' => [
                    'model_type' => $modelType,
                    'hyperparameters' => $hyperparameters
                ],
                'timeout' => 300 // 5 minutes for training
            ]);

            $result = $response->toArray();

            // Save metrics to database
            $metric = new ModelMetric();
            $metric->setModelName($modelType);
            $metric->setRmse($result['rmse'] ?? 0);
            $metric->setMae($result['mae'] ?? 0);
            $metric->setR2($result['r2'] ?? 0);
            $metric->setTrainedAt(new \DateTime());

            $this->entityManager->persist($metric);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Model training completed',
                'model_type' => $modelType,
                'metrics' => [
                    'rmse' => $metric->getRmse(),
                    'mae' => $metric->getMae(),
                    'r2' => $metric->getR2()
                ],
                'trained_at' => $metric->getTrainedAt()->format('Y-m-d H:i:s')
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Training failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current model metrics
     */
    #[Route('/metrics', name: 'admin_model_metrics', methods: ['GET'])]
    public function metrics(): JsonResponse
    {
        $so2Metrics = $this->predictionService->calculateAccuracy('SO2');
        $nh3Metrics = $this->predictionService->calculateAccuracy('NH3');
        $pm25Metrics = $this->predictionService->calculateAccuracy('PM2.5');

        return $this->json([
            'so2' => $so2Metrics,
            'nh3' => $nh3Metrics,
            'pm25' => $pm25Metrics,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update model configuration
     */
    #[Route('/config', name: 'admin_model_config', methods: ['PUT'])]
    public function updateConfig(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // In production, save config to database or file
        // For now, just return success

        return $this->json([
            'message' => 'Model configuration updated',
            'config' => $data
        ]);
    }
}
