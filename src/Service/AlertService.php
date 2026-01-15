<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\PollutionData;
use App\Repository\AlertRepository;
use Doctrine\ORM\EntityManagerInterface;

class AlertService
{
    // WHO Thresholds for SO₂ (µg/m³)
    private const SO2_GREEN = 20;
    private const SO2_YELLOW = 50;
    private const SO2_ORANGE = 100;

    // WHO Thresholds for NH₃ (µg/m³)
    private const NH3_GREEN = 30;
    private const NH3_YELLOW = 60;
    private const NH3_ORANGE = 120;

    // WHO Thresholds for PM2.5 (µg/m³)
    private const PM25_GREEN = 15;
    private const PM25_YELLOW = 35;
    private const PM25_ORANGE = 55;

    private const LEVEL_GREEN = 'green';
    private const LEVEL_YELLOW = 'yellow';
    private const LEVEL_ORANGE = 'orange';
    private const LEVEL_RED = 'red';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlertRepository $alertRepository
    ) {
    }

    /**
     * Check pollution data and create alerts if thresholds exceeded
     */
    public function checkAndCreateAlerts(PollutionData $pollutionData): array
    {
        $alerts = [];

        // Check SO₂
        $so2Alert = $this->checkPollutant('SO2', $pollutionData->getSo2(), self::SO2_GREEN, self::SO2_YELLOW, self::SO2_ORANGE);
        if ($so2Alert) {
            $so2Alert->setLatitude($pollutionData->getLatitude());
            $so2Alert->setLongitude($pollutionData->getLongitude());
            $this->entityManager->persist($so2Alert);
            $alerts[] = $so2Alert;
        }

        // Check NH₃
        $nh3Alert = $this->checkPollutant('NH3', $pollutionData->getNh3(), self::NH3_GREEN, self::NH3_YELLOW, self::NH3_ORANGE);
        if ($nh3Alert) {
            $nh3Alert->setLatitude($pollutionData->getLatitude());
            $nh3Alert->setLongitude($pollutionData->getLongitude());
            $this->entityManager->persist($nh3Alert);
            $alerts[] = $nh3Alert;
        }

        // Check PM2.5
        $pm25Alert = $this->checkPollutant('PM2.5', $pollutionData->getPm25(), self::PM25_GREEN, self::PM25_YELLOW, self::PM25_ORANGE);
        if ($pm25Alert) {
            $pm25Alert->setLatitude($pollutionData->getLatitude());
            $pm25Alert->setLongitude($pollutionData->getLongitude());
            $this->entityManager->persist($pm25Alert);
            $alerts[] = $pm25Alert;
        }

        $this->entityManager->flush();

        return $alerts;
    }

    /**
     * Check a single pollutant against thresholds
     */
    private function checkPollutant(string $pollutant, float $value, float $greenThreshold, float $yellowThreshold, float $orangeThreshold): ?Alert
    {
        $level = $this->calculateAlertLevel($value, $greenThreshold, $yellowThreshold, $orangeThreshold);

        // Only create alerts for concerning levels (yellow, orange, red)
        if ($level === self::LEVEL_GREEN) {
            // Deactivate any existing alerts for this pollutant
            $this->deactivateExistingAlerts($pollutant);
            return null;
        }

        $alert = new Alert();
        $alert->setPollutant($pollutant);
        $alert->setValue($value);
        $alert->setLevel($level);
        $alert->setMessage($this->generateAlertMessage($pollutant, $value, $level));
        $alert->setIsActive(true);
        $alert->setCreatedAt(new \DateTime());

        // Deactivate previous alerts for this pollutant
        $this->deactivateExistingAlerts($pollutant);

        return $alert;
    }

    /**
     * Calculate alert level based on thresholds
     */
    public function calculateAlertLevel(float $value, float $greenThreshold, float $yellowThreshold, float $orangeThreshold): string
    {
        if ($value < $greenThreshold) {
            return self::LEVEL_GREEN;
        } elseif ($value < $yellowThreshold) {
            return self::LEVEL_YELLOW;
        } elseif ($value < $orangeThreshold) {
            return self::LEVEL_ORANGE;
        } else {
            return self::LEVEL_RED;
        }
    }

    /**
     * Generate health message based on alert level
     */
    private function generateAlertMessage(string $pollutant, float $value, string $level): string
    {
        $messages = [
            self::LEVEL_YELLOW => [
                'SO2' => "Concentration de SO₂ modérée ({$value} µg/m³). Les personnes sensibles devraient limiter les activités prolongées en extérieur.",
                'NH3' => "Concentration de NH₃ modérée ({$value} µg/m³). Surveillance recommandée pour les personnes sensibles.",
                'PM2.5' => "Concentration de PM2.5 modérée ({$value} µg/m³). Réduire les activités extérieures intenses."
            ],
            self::LEVEL_ORANGE => [
                'SO2' => "⚠️ Concentration de SO₂ élevée ({$value} µg/m³). Évitez les activités prolongées en extérieur. Personnes sensibles: restez à l'intérieur.",
                'NH3' => "⚠️ Concentration de NH₃ élevée ({$value} µg/m³). Limitez l'exposition. Risque pour les voies respiratoires.",
                'PM2.5' => "⚠️ Concentration de PM2.5 élevée ({$value} µg/m³). Évitez l'exercice en extérieur. Portez un masque si nécessaire."
            ],
            self::LEVEL_RED => [
                'SO2' => "🚨 ALERTE ROUGE: Concentration de SO₂ dangereuse ({$value} µg/m³)! Restez à l'intérieur. Fermez les fenêtres. Évitez toute exposition.",
                'NH3' => "🚨 ALERTE ROUGE: Concentration de NH₃ dangereuse ({$value} µg/m³)! Risque sanitaire grave. Évacuez si possible.",
                'PM2.5' => "🚨 ALERTE ROUGE: Concentration de PM2.5 dangereuse ({$value} µg/m³)! Restez à l'intérieur. Utilisez un purificateur d'air."
            ]
        ];

        return $messages[$level][$pollutant] ?? "Niveau de pollution: {$level}";
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        return $this->alertRepository->findBy(['isActive' => true], ['createdAt' => 'DESC']);
    }

    /**
     * Get alert history
     */
    public function getAlertHistory(?\DateTime $startDate = null, ?\DateTime $endDate = null, ?int $limit = 50): array
    {
        $qb = $this->alertRepository->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($startDate) {
            $qb->andWhere('a.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('a.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Deactivate existing alerts for a pollutant
     */
    private function deactivateExistingAlerts(string $pollutant): void
    {
        $existingAlerts = $this->alertRepository->findBy([
            'pollutant' => $pollutant,
            'isActive' => true
        ]);

        foreach ($existingAlerts as $alert) {
            $alert->setIsActive(false);
            $alert->setResolvedAt(new \DateTime());
        }

        $this->entityManager->flush();
    }

    /**
     * Simulate alert for testing
     */
    public function simulateAlert(string $pollutant, float $value): Alert
    {
        $thresholds = [
            'SO2' => [self::SO2_GREEN, self::SO2_YELLOW, self::SO2_ORANGE],
            'NH3' => [self::NH3_GREEN, self::NH3_YELLOW, self::NH3_ORANGE],
            'PM2.5' => [self::PM25_GREEN, self::PM25_YELLOW, self::PM25_ORANGE]
        ];

        [$green, $yellow, $orange] = $thresholds[$pollutant] ?? [20, 50, 100];
        
        $alert = $this->checkPollutant($pollutant, $value, $green, $yellow, $orange);
        
        if ($alert) {
            $this->entityManager->persist($alert);
            $this->entityManager->flush();
        }

        return $alert ?? throw new \Exception("Simulation failed: value below threshold");
    }

    /**
     * Get alert statistics
     */
    public function getAlertStatistics(): array
    {
        $qb = $this->alertRepository->createQueryBuilder('a');
        
        return [
            'total_alerts' => $qb->select('COUNT(a.id)')->getQuery()->getSingleScalarResult(),
            'active_alerts' => $qb->select('COUNT(a.id)')->where('a.isActive = true')->getQuery()->getSingleScalarResult(),
            'by_level' => $this->getAlertsByLevel(),
            'by_pollutant' => $this->getAlertsByPollutant(),
            'last_24h' => $this->getRecentAlertsCount(24)
        ];
    }

    private function getAlertsByLevel(): array
    {
        $qb = $this->alertRepository->createQueryBuilder('a')
            ->select('a.level, COUNT(a.id) as count')
            ->groupBy('a.level');

        $results = $qb->getQuery()->getResult();
        
        $stats = ['green' => 0, 'yellow' => 0, 'orange' => 0, 'red' => 0];
        foreach ($results as $result) {
            $stats[$result['level']] = (int)$result['count'];
        }

        return $stats;
    }

    private function getAlertsByPollutant(): array
    {
        $qb = $this->alertRepository->createQueryBuilder('a')
            ->select('a.pollutant, COUNT(a.id) as count')
            ->groupBy('a.pollutant');

        return $qb->getQuery()->getResult();
    }

    private function getRecentAlertsCount(int $hours): int
    {
        $date = new \DateTime("-{$hours} hours");
        
        return $this->alertRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
