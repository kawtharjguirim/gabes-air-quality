<?php

namespace App\Controller;

use App\Entity\PollutionData;
use App\Repository\PollutionDataRepository;
use App\Service\AQICalculator;
use App\Service\AlertService;
use App\Service\HeatmapService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pollution')]
final class PollutionController extends AbstractController
{
    public function __construct(
        private PollutionDataRepository $pollutionDataRepository,
        private EntityManagerInterface $entityManager,
        private AQICalculator $aqiCalculator,
        private AlertService $alertService,
        private HeatmapService $heatmapService
    ) {
    }

    /**
     * Get current pollution levels
     */
    #[Route('/current', name: 'pollution_current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        $latestData = $this->pollutionDataRepository->findOneBy([], ['recordedAt' => 'DESC']);

        if (!$latestData) {
            return $this->json(['error' => 'No data available'], 404);
        }

        $aqiData = $this->aqiCalculator->calculateAQI(
            $latestData->getSo2(),
            $latestData->getNh3(),
            $latestData->getPm25()
        );

        return $this->json([
            'timestamp' => $latestData->getRecordedAt()->format('Y-m-d H:i:s'),
            'pollutants' => [
                'so2' => $latestData->getSo2(),
                'nh3' => $latestData->getNh3(),
                'pm25' => $latestData->getPm25()
            ],
            'weather' => [
                'temperature' => $latestData->getTemperature(),
                'humidity' => $latestData->getHumidity(),
                'wind_speed' => $latestData->getWindSpeed(),
                'wind_direction' => $latestData->getWindDirection(),
                'pressure' => $latestData->getPressure()
            ],
            'aqi' => $aqiData,
            'location' => [
                'latitude' => $latestData->getLatitude(),
                'longitude' => $latestData->getLongitude()
            ],
            'source' => $latestData->getSource()
        ]);
    }

    /**
     * Get pollution history
     */
    #[Route('/history', name: 'pollution_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '24h'); // 24h, 7d, 30d
        $pollutant = $request->query->get('pollutant'); // SO2, NH3, PM2.5
        
        $date = match($period) {
            '24h' => new \DateTime('-24 hours'),
            '7d' => new \DateTime('-7 days'),
            '30d' => new \DateTime('-30 days'),
            default => new \DateTime('-24 hours')
        };

        $qb = $this->pollutionDataRepository->createQueryBuilder('pd')
            ->where('pd.recordedAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('pd.recordedAt', 'ASC');

        $data = $qb->getQuery()->getResult();

        $history = array_map(function(PollutionData $pd) use ($pollutant) {
            $result = [
                'timestamp' => $pd->getRecordedAt()->format('Y-m-d H:i:s'),
            ];

            if ($pollutant) {
                $result['value'] = match($pollutant) {
                    'SO2' => $pd->getSo2(),
                    'NH3' => $pd->getNh3(),
                    'PM2.5' => $pd->getPm25(),
                    default => null
                };
            } else {
                $result['so2'] = $pd->getSo2();
                $result['nh3'] = $pd->getNh3();
                $result['pm25'] = $pd->getPm25();
                $result['aqi'] = $pd->getAqi();
            }

            return $result;
        }, $data);

        return $this->json([
            'period' => $period,
            'pollutant' => $pollutant ?? 'all',
            'count' => count($history),
            'data' => $history
        ]);
    }

    /**
     * Get AQI information
     */
    #[Route('/aqi', name: 'pollution_aqi', methods: ['GET'])]
    public function aqi(): JsonResponse
    {
        $latestData = $this->pollutionDataRepository->findOneBy([], ['recordedAt' => 'DESC']);

        if (!$latestData) {
            return $this->json(['error' => 'No data available'], 404);
        }

        $aqiData = $this->aqiCalculator->calculateAQI(
            $latestData->getSo2(),
            $latestData->getNh3(),
            $latestData->getPm25()
        );

        return $this->json($aqiData);
    }

    /**
     * Get map data for visualization
     */
    #[Route('/map-data', name: 'pollution_map_data', methods: ['GET'])]
    public function mapData(): JsonResponse
    {
        $data = $this->pollutionDataRepository->createQueryBuilder('pd')
            ->where('pd.latitude IS NOT NULL')
            ->andWhere('pd.longitude IS NOT NULL')
            ->andWhere('pd.recordedAt >= :time')
            ->setParameter('time', new \DateTime('-1 hour'))
            ->getQuery()
            ->getResult();

        $points = array_map(function(PollutionData $pd) {
            return [
                'lat' => $pd->getLatitude(),
                'lng' => $pd->getLongitude(),
                'so2' => $pd->getSo2(),
                'nh3' => $pd->getNh3(),
                'pm25' => $pd->getPm25(),
                'aqi' => $pd->getAqi(),
                'timestamp' => $pd->getRecordedAt()->format('Y-m-d H:i:s')
            ];
        }, $data);

        return $this->json([
            'points' => $points,
            'count' => count($points)
        ]);
    }

    /**
     * Get heatmap data
     */
    #[Route('/heatmap', name: 'pollution_heatmap', methods: ['GET'])]
    public function heatmap(Request $request): JsonResponse
    {
        $pollutant = $request->query->get('pollutant', 'SO2');
        $timeframe = $request->query->get('timeframe', 'current');

        $heatmapData = $this->heatmapService->generateHeatmapData($pollutant, $timeframe);

        return $this->json($heatmapData);
    }

    /**
     * Get zone risk levels
     */
    #[Route('/zones', name: 'pollution_zones', methods: ['GET'])]
    public function zones(): JsonResponse
    {
        $zones = $this->heatmapService->getZoneRiskLevels();

        return $this->json([
            'zones' => $zones,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Add new pollution data
     */
    #[Route('/data', name: 'pollution_add_data', methods: ['POST'])]
    public function addData(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $pollutionData = new PollutionData();
        $pollutionData->setSo2($data['so2'] ?? 0);
        $pollutionData->setNh3($data['nh3'] ?? 0);
        $pollutionData->setPm25($data['pm25'] ?? 0);
        $pollutionData->setTemperature($data['temperature'] ?? 20);
        $pollutionData->setHumidity($data['humidity'] ?? 50);
        $pollutionData->setWindSpeed($data['wind_speed'] ?? 0);
        $pollutionData->setWindDirection($data['wind_direction'] ?? 0);
        
        if (isset($data['pressure'])) {
            $pollutionData->setPressure($data['pressure']);
        }
        
        if (isset($data['latitude']) && isset($data['longitude'])) {
            $pollutionData->setLatitude($data['latitude']);
            $pollutionData->setLongitude($data['longitude']);
        }

        $pollutionData->setRecordedAt(new \DateTime($data['timestamp'] ?? 'now'));
        $pollutionData->setSource($data['source'] ?? 'api');

        // Calculate AQI
        $aqiData = $this->aqiCalculator->calculateAQI(
            $pollutionData->getSo2(),
            $pollutionData->getNh3(),
            $pollutionData->getPm25()
        );
        $pollutionData->setAqi($aqiData['overall']);

        $this->entityManager->persist($pollutionData);
        $this->entityManager->flush();

        // Check for alerts
        $alerts = $this->alertService->checkAndCreateAlerts($pollutionData);

        return $this->json([
            'message' => 'Data added successfully',
            'id' => $pollutionData->getId(),
            'aqi' => $aqiData,
            'alerts_created' => count($alerts)
        ], 201);
    }

    /**
     * Get statistics
     */
    #[Route('/stats', name: 'pollution_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd');

        $totalRecords = $qb->select('COUNT(pd.id)')->getQuery()->getSingleScalarResult();

        $avgSo2 = $qb->select('AVG(pd.so2)')->getQuery()->getSingleScalarResult();
        $avgNh3 = $qb->select('AVG(pd.nh3)')->getQuery()->getSingleScalarResult();
        $avgPm25 = $qb->select('AVG(pd.pm25)')->getQuery()->getSingleScalarResult();

        $maxSo2 = $qb->select('MAX(pd.so2)')->getQuery()->getSingleScalarResult();
        $maxNh3 = $qb->select('MAX(pd.nh3)')->getQuery()->getSingleScalarResult();
        $maxPm25 = $qb->select('MAX(pd.pm25)')->getQuery()->getSingleScalarResult();

        return $this->json([
            'total_records' => $totalRecords,
            'averages' => [
                'so2' => round($avgSo2, 2),
                'nh3' => round($avgNh3, 2),
                'pm25' => round($avgPm25, 2)
            ],
            'maximums' => [
                'so2' => round($maxSo2, 2),
                'nh3' => round($maxNh3, 2),
                'pm25' => round($maxPm25, 2)
            ]
        ]);
    }
}
