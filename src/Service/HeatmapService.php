<?php

namespace App\Service;

use App\Repository\PollutionDataRepository;

class HeatmapService
{
    public function __construct(
        private PollutionDataRepository $pollutionDataRepository
    ) {
    }

    /**
     * Generate heatmap data for a specific pollutant
     */
    public function generateHeatmapData(string $pollutant = 'SO2', ?string $timeframe = 'current'): array
    {
        $data = $this->getPollutionDataForHeatmap($timeframe);

        $heatmapPoints = [];

        foreach ($data as $record) {
            if ($record->getLatitude() && $record->getLongitude()) {
                $value = match($pollutant) {
                    'SO2' => $record->getSo2(),
                    'NH3' => $record->getNh3(),
                    'PM2.5' => $record->getPm25(),
                    'AQI' => $record->getAqi(),
                    default => 0
                };

                $heatmapPoints[] = [
                    'lat' => $record->getLatitude(),
                    'lng' => $record->getLongitude(),
                    'value' => $value,
                    'intensity' => $this->calculateIntensity($pollutant, $value),
                    'timestamp' => $record->getRecordedAt()->format('Y-m-d H:i:s')
                ];
            }
        }

        return [
            'pollutant' => $pollutant,
            'timeframe' => $timeframe,
            'points' => $heatmapPoints,
            'count' => count($heatmapPoints),
            'max_value' => !empty($heatmapPoints) ? max(array_column($heatmapPoints, 'value')) : 0,
            'min_value' => !empty($heatmapPoints) ? min(array_column($heatmapPoints, 'value')) : 0
        ];
    }

    /**
     * Get pollution data based on timeframe
     */
    private function getPollutionDataForHeatmap(?string $timeframe): array
    {
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd')
            ->where('pd.latitude IS NOT NULL')
            ->andWhere('pd.longitude IS NOT NULL')
            ->orderBy('pd.recordedAt', 'DESC');

        if ($timeframe === 'current') {
            // Last hour only
            $qb->andWhere('pd.recordedAt >= :time')
               ->setParameter('time', new \DateTime('-1 hour'));
        } elseif ($timeframe === 'last_24h') {
            $qb->andWhere('pd.recordedAt >= :time')
               ->setParameter('time', new \DateTime('-24 hours'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calculate intensity (0-1) for heatmap coloring
     */
    private function calculateIntensity(string $pollutant, float $value): float
    {
        $maxValues = [
            'SO2' => 200,
            'NH3' => 200,
            'PM2.5' => 100,
            'AQI' => 300
        ];

        $max = $maxValues[$pollutant] ?? 100;
        $intensity = min($value / $max, 1.0);

        return round($intensity, 3);
    }

    /**
     * Generate grid-based heatmap for better visualization
     */
    public function generateGridHeatmap(string $pollutant = 'SO2', float $gridSize = 0.01): array
    {
        $data = $this->getPollutionDataForHeatmap('current');

        // Group data into grid cells
        $grid = [];

        foreach ($data as $record) {
            if (!$record->getLatitude() || !$record->getLongitude()) {
                continue;
            }

            $value = match($pollutant) {
                'SO2' => $record->getSo2(),
                'NH3' => $record->getNh3(),
                'PM2.5' => $record->getPm25(),
                'AQI' => $record->getAqi(),
                default => 0
            };

            // Round coordinates to grid
            $gridLat = round($record->getLatitude() / $gridSize) * $gridSize;
            $gridLng = round($record->getLongitude() / $gridSize) * $gridSize;
            $key = "{$gridLat}_{$gridLng}";

            if (!isset($grid[$key])) {
                $grid[$key] = [
                    'lat' => $gridLat,
                    'lng' => $gridLng,
                    'values' => [],
                    'count' => 0
                ];
            }

            $grid[$key]['values'][] = $value;
            $grid[$key]['count']++;
        }

        // Calculate average for each grid cell
        $gridPoints = [];
        foreach ($grid as $cell) {
            $avgValue = array_sum($cell['values']) / $cell['count'];
            $gridPoints[] = [
                'lat' => $cell['lat'],
                'lng' => $cell['lng'],
                'value' => round($avgValue, 2),
                'count' => $cell['count'],
                'intensity' => $this->calculateIntensity($pollutant, $avgValue)
            ];
        }

        return [
            'pollutant' => $pollutant,
            'grid_size' => $gridSize,
            'cells' => $gridPoints,
            'total_cells' => count($gridPoints)
        ];
    }

    /**
     * Get coordinates of measurement points
     */
    public function getMeasurementPoints(): array
    {
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd')
            ->select('DISTINCT pd.latitude, pd.longitude, pd.source')
            ->where('pd.latitude IS NOT NULL')
            ->andWhere('pd.longitude IS NOT NULL');

        $points = $qb->getQuery()->getResult();

        return array_map(function($point) {
            return [
                'lat' => (float)$point['latitude'],
                'lng' => (float)$point['longitude'],
                'source' => $point['source']
            ];
        }, $points);
    }

    /**
     * Get zone risk levels for Gabès map
     */
    public function getZoneRiskLevels(): array
    {
        // Define zones in Gabès (predefined areas)
        $zones = [
            'industrial' => ['lat' => 33.8869, 'lng' => 10.0982],
            'city_center' => ['lat' => 33.8815, 'lng' => 10.0982],
            'residential' => ['lat' => 33.8900, 'lng' => 10.1100],
            'coastal' => ['lat' => 33.8700, 'lng' => 10.1200]
        ];

        $zoneRisks = [];

        foreach ($zones as $zoneName => $coords) {
            // Find nearby pollution data (within ~1km)
            $nearbyData = $this->getNearbyPollution($coords['lat'], $coords['lng'], 0.01);
            
            if (!empty($nearbyData)) {
                $avgAQI = array_sum(array_column($nearbyData, 'aqi')) / count($nearbyData);
                $level = $this->getRiskLevel($avgAQI);
            } else {
                $avgAQI = null;
                $level = 'unknown';
            }

            $zoneRisks[] = [
                'zone' => $zoneName,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'aqi' => $avgAQI ? round($avgAQI, 1) : null,
                'risk_level' => $level,
                'color' => $this->getRiskColor($level)
            ];
        }

        return $zoneRisks;
    }

    /**
     * Get pollution data near a coordinate
     */
    private function getNearbyPollution(float $lat, float $lng, float $radius): array
    {
        $data = $this->pollutionDataRepository->createQueryBuilder('pd')
            ->where('pd.latitude IS NOT NULL')
            ->andWhere('pd.longitude IS NOT NULL')
            ->andWhere('pd.latitude BETWEEN :latMin AND :latMax')
            ->andWhere('pd.longitude BETWEEN :lngMin AND :lngMax')
            ->andWhere('pd.recordedAt >= :time')
            ->setParameter('latMin', $lat - $radius)
            ->setParameter('latMax', $lat + $radius)
            ->setParameter('lngMin', $lng - $radius)
            ->setParameter('lngMax', $lng + $radius)
            ->setParameter('time', new \DateTime('-1 hour'))
            ->getQuery()
            ->getResult();

        return array_map(fn($pd) => [
            'aqi' => $pd->getAqi(),
            'so2' => $pd->getSo2(),
            'nh3' => $pd->getNh3(),
            'pm25' => $pd->getPm25()
        ], $data);
    }

    /**
     * Determine risk level from AQI
     */
    private function getRiskLevel(?float $aqi): string
    {
        if ($aqi === null) return 'unknown';
        if ($aqi <= 50) return 'low';
        if ($aqi <= 100) return 'moderate';
        if ($aqi <= 150) return 'high';
        return 'very_high';
    }

    /**
     * Get color for risk level
     */
    private function getRiskColor(string $level): string
    {
        return match($level) {
            'low' => '#00E400',
            'moderate' => '#FFFF00',
            'high' => '#FF7E00',
            'very_high' => '#FF0000',
            default => '#CCCCCC'
        };
    }
}
