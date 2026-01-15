<?php

namespace App\Service;

use App\Entity\PollutionData;
use App\Repository\PollutionDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DataImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PollutionDataRepository $pollutionDataRepository,
        private AQICalculator $aqiCalculator
    ) {
    }

    /**
     * Import pollution data from CSV file
     */
    public function importFromCsv(UploadedFile $file): array
    {
        $imported = 0;
        $errors = [];
        $skipped = 0;

        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            $headers = fgetcsv($handle); // Read header row
            
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $data = array_combine($headers, $row);
                    $pollutionData = $this->createPollutionDataFromArray($data);
                    
                    $this->entityManager->persist($pollutionData);
                    $imported++;

                    // Batch flush every 100 records for performance
                    if ($imported % 100 === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }

                } catch (\Exception $e) {
                    $errors[] = "Row {$imported}: {$e->getMessage()}";
                    $skipped++;
                }
            }

            fclose($handle);
            $this->entityManager->flush();
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Create PollutionData entity from array
     */
    private function createPollutionDataFromArray(array $data): PollutionData
    {
        $pollutionData = new PollutionData();

        // Required fields
        $pollutionData->setSo2((float)($data['so2'] ?? $data['SO2'] ?? 0));
        $pollutionData->setNh3((float)($data['nh3'] ?? $data['NH3'] ?? 0));
        $pollutionData->setPm25((float)($data['pm25'] ?? $data['PM2.5'] ?? $data['pm2.5'] ?? 0));
        
        $pollutionData->setTemperature((float)($data['temperature'] ?? $data['temp'] ?? 20));
        $pollutionData->setHumidity((float)($data['humidity'] ?? 50));
        $pollutionData->setWindSpeed((float)($data['wind_speed'] ?? $data['windSpeed'] ?? 0));
        $pollutionData->setWindDirection((float)($data['wind_direction'] ?? $data['windDirection'] ?? 0));

        // Optional fields
        if (isset($data['pressure'])) {
            $pollutionData->setPressure((float)$data['pressure']);
        }

        if (isset($data['latitude']) && isset($data['longitude'])) {
            $pollutionData->setLatitude((float)$data['latitude']);
            $pollutionData->setLongitude((float)$data['longitude']);
        }

        // Timestamp
        if (isset($data['timestamp']) || isset($data['recorded_at']) || isset($data['date'])) {
            $dateString = $data['timestamp'] ?? $data['recorded_at'] ?? $data['date'];
            $pollutionData->setRecordedAt(new \DateTime($dateString));
        } else {
            $pollutionData->setRecordedAt(new \DateTime());
        }

        // Source
        $pollutionData->setSource($data['source'] ?? 'csv_import');

        // Calculate and set AQI
        $aqiData = $this->aqiCalculator->calculateAQI(
            $pollutionData->getSo2(),
            $pollutionData->getNh3(),
            $pollutionData->getPm25()
        );
        $pollutionData->setAqi($aqiData['overall']);

        return $pollutionData;
    }

    /**
     * Get data quality statistics
     */
    public function getDataQuality(): array
    {
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd');
        
        $totalRecords = $qb->select('COUNT(pd.id)')->getQuery()->getSingleScalarResult();

        if ($totalRecords == 0) {
            return ['error' => 'No data available'];
        }

        return [
            'total_records' => $totalRecords,
            'date_range' => $this->getDateRange(),
            'missing_values' => $this->getMissingValuesStats($totalRecords),
            'outliers' => $this->detectOutliers(),
            'data_sources' => $this->getDataSources(),
            'completeness' => $this->calculateCompleteness()
        ];
    }

    private function getDateRange(): array
    {
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd');
        
        $minDate = $qb->select('MIN(pd.recordedAt)')->getQuery()->getSingleScalarResult();
        $maxDate = $qb->select('MAX(pd.recordedAt)')->getQuery()->getSingleScalarResult();

        return [
            'start' => $minDate ? (new \DateTime($minDate))->format('Y-m-d H:i:s') : null,
            'end' => $maxDate ? (new \DateTime($maxDate))->format('Y-m-d H:i:s') : null
        ];
    }

    private function getMissingValuesStats(int $total): array
    {
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd');
        
        $missingLatitude = $qb->select('COUNT(pd.id)')
            ->where('pd.latitude IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $missingLongitude = $qb->select('COUNT(pd.id)')
            ->where('pd.longitude IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $missingPressure = $qb->select('COUNT(pd.id)')
            ->where('pd.pressure IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'latitude' => [
                'count' => $missingLatitude,
                'percentage' => round(($missingLatitude / $total) * 100, 2)
            ],
            'longitude' => [
                'count' => $missingLongitude,
                'percentage' => round(($missingLongitude / $total) * 100, 2)
            ],
            'pressure' => [
                'count' => $missingPressure,
                'percentage' => round(($missingPressure / $total) * 100, 2)
            ]
        ];
    }

    private function detectOutliers(): array
    {
        // Detect values exceeding reasonable thresholds
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd');
        
        $outliers = [];
        
        $so2Outliers = $qb->select('COUNT(pd.id)')
            ->where('pd.so2 > 500')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($so2Outliers > 0) {
            $outliers['so2'] = $so2Outliers;
        }

        $nh3Outliers = $qb->select('COUNT(pd.id)')
            ->where('pd.nh3 > 500')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($nh3Outliers > 0) {
            $outliers['nh3'] = $nh3Outliers;
        }

        $pm25Outliers = $qb->select('COUNT(pd.id)')
            ->where('pd.pm25 > 200')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($pm25Outliers > 0) {
            $outliers['pm25'] = $pm25Outliers;
        }

        return $outliers;
    }

    private function getDataSources(): array
    {
        return $this->pollutionDataRepository->createQueryBuilder('pd')
            ->select('pd.source, COUNT(pd.id) as count')
            ->groupBy('pd.source')
            ->getQuery()
            ->getResult();
    }

    private function calculateCompleteness(): float
    {
        $qb = $this->pollutionDataRepository->createQueryBuilder('pd');
        
        $total = $qb->select('COUNT(pd.id)')->getQuery()->getSingleScalarResult();
        
        if ($total == 0) {
            return 0;
        }

        $complete = $qb->select('COUNT(pd.id)')
            ->where('pd.latitude IS NOT NULL')
            ->andWhere('pd.longitude IS NOT NULL')
            ->andWhere('pd.pressure IS NOT NULL')
            ->andWhere('pd.aqi IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return round(($complete / $total) * 100, 2);
    }

    /**
     * Get preview of data (first N records)
     */
    public function getDataPreview(int $limit = 10): array
    {
        $data = $this->pollutionDataRepository->findBy([], ['recordedAt' => 'DESC'], $limit);

        return array_map(function(PollutionData $pd) {
            return [
                'id' => $pd->getId(),
                'so2' => $pd->getSo2(),
                'nh3' => $pd->getNh3(),
                'pm25' => $pd->getPm25(),
                'temperature' => $pd->getTemperature(),
                'humidity' => $pd->getHumidity(),
                'wind_speed' => $pd->getWindSpeed(),
                'aqi' => $pd->getAqi(),
                'recorded_at' => $pd->getRecordedAt()->format('Y-m-d H:i:s'),
                'source' => $pd->getSource()
            ];
        }, $data);
    }

    /**
     * Validate CSV file format
     */
    public function validateCsvFormat(UploadedFile $file): array
    {
        $errors = [];
        $requiredColumns = ['so2', 'nh3', 'pm25', 'temperature', 'humidity', 'wind_speed'];

        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            $headers = fgetcsv($handle);
            fclose($handle);

            $headersLower = array_map('strtolower', $headers);

            foreach ($requiredColumns as $required) {
                if (!in_array(strtolower($required), $headersLower)) {
                    $errors[] = "Missing required column: {$required}";
                }
            }
        } else {
            $errors[] = "Unable to read file";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'headers' => $headers ?? []
        ];
    }
}
