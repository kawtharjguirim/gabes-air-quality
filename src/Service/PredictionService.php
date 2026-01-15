<?php

namespace App\Service;

use App\Entity\Prediction;
use App\Entity\PollutionData;
use App\Repository\PredictionRepository;
use App\Repository\PollutionDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PredictionService
{
    private string $pythonApiUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PredictionRepository $predictionRepository,
        private PollutionDataRepository $pollutionDataRepository,
        private HttpClientInterface $httpClient
    ) {
        // URL of your Python ML API (Flask/FastAPI)
        $this->pythonApiUrl = $_ENV['PYTHON_ML_API_URL'] ?? 'http://localhost:5000';
    }

    /**
     * Generate predictions for next N hours
     */
    public function generatePredictions(int $hoursAhead = 6, ?string $modelVersion = 'v1.0'): array
    {
        $latestData = $this->getLatestPollutionData();
        
        if (!$latestData) {
            throw new \Exception('No pollution data available for prediction');
        }

        $predictions = [];

        foreach (['SO2', 'NH3', 'PM2.5'] as $pollutant) {
            for ($hour = 1; $hour <= $hoursAhead; $hour++) {
                $prediction = $this->predict($pollutant, $hour, $latestData, $modelVersion);
                $this->entityManager->persist($prediction);
                $predictions[] = $prediction;
            }
        }

        $this->entityManager->flush();

        return $predictions;
    }

    /**
     * Make a single prediction by calling Python ML model
     */
    private function predict(string $pollutant, int $hoursAhead, PollutionData $latestData, string $modelVersion): Prediction
    {
        try {
            // Prepare features for ML model
            $features = $this->prepareFeatures($latestData, $pollutant);

            // Call Python API
            $response = $this->httpClient->request('POST', "{$this->pythonApiUrl}/predict", [
                'json' => [
                    'pollutant' => $pollutant,
                    'hours_ahead' => $hoursAhead,
                    'features' => $features
                ],
                'timeout' => 10
            ]);

            $data = $response->toArray();
            $predictedValue = $data['predicted_value'] ?? $this->fallbackPrediction($pollutant, $latestData);

        } catch (\Exception $e) {
            // Fallback if Python API is unavailable
            $predictedValue = $this->fallbackPrediction($pollutant, $latestData);
        }

        $prediction = new Prediction();
        $prediction->setPollutant($pollutant);
        $prediction->setPredictedValue($predictedValue);
        $prediction->setHoursAhead($hoursAhead);
        $prediction->setCreatedAt(new \DateTime());
        $prediction->setPredictionFor((new \DateTime())->modify("+{$hoursAhead} hours"));
        $prediction->setModelVersion($modelVersion);

        return $prediction;
    }

    /**
     * Prepare features from pollution data for ML model
     */
    private function prepareFeatures(PollutionData $data, string $pollutant): array
    {
        return [
            'so2' => $data->getSo2(),
            'nh3' => $data->getNh3(),
            'pm25' => $data->getPm25(),
            'temperature' => $data->getTemperature(),
            'humidity' => $data->getHumidity(),
            'wind_speed' => $data->getWindSpeed(),
            'wind_direction' => $data->getWindDirection(),
            'pressure' => $data->getPressure(),
            'hour' => (int)$data->getRecordedAt()->format('H'),
            'day_of_week' => (int)$data->getRecordedAt()->format('N'),
            'month' => (int)$data->getRecordedAt()->format('m')
        ];
    }

    /**
     * Simple fallback prediction (persistence model)
     */
    private function fallbackPrediction(string $pollutant, PollutionData $latestData): float
    {
        return match($pollutant) {
            'SO2' => $latestData->getSo2(),
            'NH3' => $latestData->getNh3(),
            'PM2.5' => $latestData->getPm25(),
            default => 0.0
        };
    }

    /**
     * Get latest pollution data
     */
    private function getLatestPollutionData(): ?PollutionData
    {
        return $this->pollutionDataRepository->findOneBy([], ['recordedAt' => 'DESC']);
    }

    /**
     * Get predictions for next N hours
     */
    public function getNextHoursPredictions(int $hours = 6): array
    {
        $now = new \DateTime();
        $future = (new \DateTime())->modify("+{$hours} hours");

        return $this->predictionRepository->createQueryBuilder('p')
            ->where('p.predictionFor >= :now')
            ->andWhere('p.predictionFor <= :future')
            ->andWhere('p.createdAt = (
                SELECT MAX(p2.createdAt)
                FROM App\Entity\Prediction p2
                WHERE p2.pollutant = p.pollutant
                AND p2.hoursAhead = p.hoursAhead
            )')
            ->setParameter('now', $now)
            ->setParameter('future', $future)
            ->orderBy('p.predictionFor', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Update actual values for predictions (for model evaluation)
     */
    public function updateActualValues(): int
    {
        $updated = 0;
        $predictions = $this->predictionRepository->createQueryBuilder('p')
            ->where('p.actualValue IS NULL')
            ->andWhere('p.predictionFor < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();

        foreach ($predictions as $prediction) {
            $actualData = $this->findActualData($prediction->getPredictionFor(), $prediction->getPollutant());
            
            if ($actualData) {
                $prediction->setActualValue($actualData);
                $updated++;
            }
        }

        $this->entityManager->flush();

        return $updated;
    }

    /**
     * Find actual pollution value at specific time
     */
    private function findActualData(\DateTimeInterface $time, string $pollutant): ?float
    {
        $tolerance = 30; // minutes
        $start = \DateTime::createFromInterface($time)->modify("-{$tolerance} minutes");
        $end = \DateTime::createFromInterface($time)->modify("+{$tolerance} minutes");

        $data = $this->pollutionDataRepository->createQueryBuilder('pd')
            ->where('pd.recordedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$data) {
            return null;
        }

        return match($pollutant) {
            'SO2' => $data->getSo2(),
            'NH3' => $data->getNh3(),
            'PM2.5' => $data->getPm25(),
            default => null
        };
    }

    /**
     * Calculate prediction accuracy metrics
     */
    public function calculateAccuracy(?string $pollutant = null, ?int $hoursAhead = null): array
    {
        $qb = $this->predictionRepository->createQueryBuilder('p')
            ->where('p.actualValue IS NOT NULL');

        if ($pollutant) {
            $qb->andWhere('p.pollutant = :pollutant')
               ->setParameter('pollutant', $pollutant);
        }

        if ($hoursAhead) {
            $qb->andWhere('p.hoursAhead = :hours')
               ->setParameter('hours', $hoursAhead);
        }

        $predictions = $qb->getQuery()->getResult();

        if (empty($predictions)) {
            return ['error' => 'No data available for evaluation'];
        }

        $errors = [];
        $squaredErrors = [];
        $absoluteErrors = [];
        $actualValues = [];
        $predictedValues = [];

        foreach ($predictions as $pred) {
            $error = $pred->getPredictedValue() - $pred->getActualValue();
            $errors[] = $error;
            $squaredErrors[] = $error ** 2;
            $absoluteErrors[] = abs($error);
            $actualValues[] = $pred->getActualValue();
            $predictedValues[] = $pred->getPredictedValue();
        }

        $rmse = sqrt(array_sum($squaredErrors) / count($squaredErrors));
        $mae = array_sum($absoluteErrors) / count($absoluteErrors);
        $r2 = $this->calculateR2($actualValues, $predictedValues);

        return [
            'rmse' => round($rmse, 2),
            'mae' => round($mae, 2),
            'r2' => round($r2, 4),
            'sample_size' => count($predictions),
            'pollutant' => $pollutant ?? 'all',
            'hours_ahead' => $hoursAhead ?? 'all'
        ];
    }

    /**
     * Calculate R² (coefficient of determination)
     */
    private function calculateR2(array $actual, array $predicted): float
    {
        $meanActual = array_sum($actual) / count($actual);
        
        $ssTotal = array_sum(array_map(fn($y) => ($y - $meanActual) ** 2, $actual));
        $ssResidual = array_sum(array_map(fn($y, $yHat) => ($y - $yHat) ** 2, $actual, $predicted));

        if ($ssTotal == 0) {
            return 0;
        }

        return 1 - ($ssResidual / $ssTotal);
    }

    /**
     * Get prediction vs actual comparison data for charts
     */
    public function getPredictionComparison(string $pollutant, int $limit = 100): array
    {
        $predictions = $this->predictionRepository->createQueryBuilder('p')
            ->where('p.pollutant = :pollutant')
            ->andWhere('p.actualValue IS NOT NULL')
            ->setParameter('pollutant', $pollutant)
            ->orderBy('p.predictionFor', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($predictions as $pred) {
            $data[] = [
                'timestamp' => $pred->getPredictionFor()->format('Y-m-d H:i:s'),
                'predicted' => $pred->getPredictedValue(),
                'actual' => $pred->getActualValue(),
                'error' => abs($pred->getPredictedValue() - $pred->getActualValue()),
                'hours_ahead' => $pred->getHoursAhead()
            ];
        }

        return $data;
    }
}
