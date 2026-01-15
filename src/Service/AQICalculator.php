<?php

namespace App\Service;

class AQICalculator
{
    /**
     * Calculate overall Air Quality Index based on multiple pollutants
     * Using US EPA AQI calculation method
     */
    public function calculateAQI(float $so2, float $nh3, float $pm25): array
    {
        $so2AQI = $this->calculateSO2AQI($so2);
        $nh3AQI = $this->calculateNH3AQI($nh3);
        $pm25AQI = $this->calculatePM25AQI($pm25);

        // Overall AQI is the maximum of all pollutant AQIs
        $overallAQI = max($so2AQI, $nh3AQI, $pm25AQI);

        return [
            'overall' => round($overallAQI),
            'level' => $this->getAQILevel($overallAQI),
            'category' => $this->getAQICategory($overallAQI),
            'color' => $this->getAQIColor($overallAQI),
            'health_message' => $this->getHealthMessage($overallAQI),
            'pollutants' => [
                'so2' => round($so2AQI),
                'nh3' => round($nh3AQI),
                'pm25' => round($pm25AQI)
            ],
            'dominant_pollutant' => $this->getDominantPollutant($so2AQI, $nh3AQI, $pm25AQI)
        ];
    }

    /**
     * Calculate AQI for SO₂ (µg/m³)
     */
    private function calculateSO2AQI(float $concentration): float
    {
        // Breakpoints: [C_low, C_high, I_low, I_high]
        $breakpoints = [
            [0, 20, 0, 50],       // Good
            [20, 50, 51, 100],    // Moderate
            [50, 100, 101, 150],  // Unhealthy for Sensitive
            [100, 200, 151, 200], // Unhealthy
            [200, 500, 201, 300], // Very Unhealthy
            [500, 1000, 301, 500] // Hazardous
        ];

        return $this->calculateAQIFromBreakpoints($concentration, $breakpoints);
    }

    /**
     * Calculate AQI for NH₃ (µg/m³)
     */
    private function calculateNH3AQI(float $concentration): float
    {
        // Custom breakpoints for NH₃
        $breakpoints = [
            [0, 30, 0, 50],
            [30, 60, 51, 100],
            [60, 120, 101, 150],
            [120, 200, 151, 200],
            [200, 400, 201, 300],
            [400, 800, 301, 500]
        ];

        return $this->calculateAQIFromBreakpoints($concentration, $breakpoints);
    }

    /**
     * Calculate AQI for PM2.5 (µg/m³)
     */
    private function calculatePM25AQI(float $concentration): float
    {
        // US EPA PM2.5 breakpoints
        $breakpoints = [
            [0, 12, 0, 50],
            [12.1, 35.4, 51, 100],
            [35.5, 55.4, 101, 150],
            [55.5, 150.4, 151, 200],
            [150.5, 250.4, 201, 300],
            [250.5, 500, 301, 500]
        ];

        return $this->calculateAQIFromBreakpoints($concentration, $breakpoints);
    }

    /**
     * Generic AQI calculation using breakpoints
     */
    private function calculateAQIFromBreakpoints(float $concentration, array $breakpoints): float
    {
        foreach ($breakpoints as [$cLow, $cHigh, $iLow, $iHigh]) {
            if ($concentration >= $cLow && $concentration <= $cHigh) {
                return (($iHigh - $iLow) / ($cHigh - $cLow)) * ($concentration - $cLow) + $iLow;
            }
        }

        // If concentration exceeds all breakpoints, return max AQI
        return 500;
    }

    /**
     * Get AQI level (Good, Moderate, etc.)
     */
    private function getAQILevel(float $aqi): string
    {
        if ($aqi <= 50) return 'Good';
        if ($aqi <= 100) return 'Moderate';
        if ($aqi <= 150) return 'Unhealthy for Sensitive Groups';
        if ($aqi <= 200) return 'Unhealthy';
        if ($aqi <= 300) return 'Very Unhealthy';
        return 'Hazardous';
    }

    /**
     * Get simplified category
     */
    private function getAQICategory(float $aqi): string
    {
        if ($aqi <= 50) return 'green';
        if ($aqi <= 100) return 'yellow';
        if ($aqi <= 200) return 'orange';
        return 'red';
    }

    /**
     * Get color code for AQI
     */
    private function getAQIColor(float $aqi): string
    {
        if ($aqi <= 50) return '#00E400';      // Green
        if ($aqi <= 100) return '#FFFF00';     // Yellow
        if ($aqi <= 150) return '#FF7E00';     // Orange
        if ($aqi <= 200) return '#FF0000';     // Red
        if ($aqi <= 300) return '#8F3F97';     // Purple
        return '#7E0023';                       // Maroon
    }

    /**
     * Get health message for AQI level
     */
    private function getHealthMessage(float $aqi): string
    {
        if ($aqi <= 50) {
            return "La qualité de l'air est satisfaisante. Profitez de vos activités en plein air.";
        }
        if ($aqi <= 100) {
            return "Qualité de l'air acceptable. Personnes sensibles: limitez les efforts prolongés.";
        }
        if ($aqi <= 150) {
            return "⚠️ Les personnes sensibles peuvent ressentir des effets. Limitez les activités extérieures prolongées.";
        }
        if ($aqi <= 200) {
            return "⚠️ Tout le monde peut commencer à ressentir des effets. Évitez les activités extérieures prolongées.";
        }
        if ($aqi <= 300) {
            return "🚨 Alerte sanitaire: risques pour la santé accrus. Évitez les sorties. Personnes à risque: restez à l'intérieur.";
        }
        return "🚨 ALERTE URGENTE: Urgence sanitaire. Restez à l'intérieur. Fermez portes et fenêtres.";
    }

    /**
     * Determine which pollutant is causing the highest AQI
     */
    private function getDominantPollutant(float $so2AQI, float $nh3AQI, float $pm25AQI): string
    {
        $pollutants = [
            'SO2' => $so2AQI,
            'NH3' => $nh3AQI,
            'PM2.5' => $pm25AQI
        ];

        return array_search(max($pollutants), $pollutants);
    }

    /**
     * Get detailed AQI information for dashboard
     */
    public function getDetailedAQIInfo(float $aqi): array
    {
        return [
            'value' => round($aqi),
            'level' => $this->getAQILevel($aqi),
            'category' => $this->getAQICategory($aqi),
            'color' => $this->getAQIColor($aqi),
            'health_message' => $this->getHealthMessage($aqi),
            'recommendations' => $this->getRecommendations($aqi)
        ];
    }

    /**
     * Get specific recommendations based on AQI
     */
    private function getRecommendations(float $aqi): array
    {
        if ($aqi <= 50) {
            return [
                'general' => 'Conditions idéales pour toutes activités en extérieur',
                'sensitive' => 'Aucune restriction',
                'outdoor' => 'Toutes activités recommandées'
            ];
        }
        if ($aqi <= 100) {
            return [
                'general' => 'Acceptable pour la plupart des gens',
                'sensitive' => 'Limitez les efforts prolongés si vous êtes sensible',
                'outdoor' => 'Activités normales possibles'
            ];
        }
        if ($aqi <= 150) {
            return [
                'general' => 'Réduisez les activités extérieures intenses',
                'sensitive' => 'Évitez les efforts prolongés en extérieur',
                'outdoor' => 'Préférez les activités en intérieur'
            ];
        }
        if ($aqi <= 200) {
            return [
                'general' => 'Évitez les activités extérieures prolongées',
                'sensitive' => 'Restez à l\'intérieur',
                'outdoor' => 'Activités en intérieur uniquement'
            ];
        }
        return [
            'general' => 'Restez à l\'intérieur. Fermez portes et fenêtres',
            'sensitive' => 'Évacuez si possible. Urgence sanitaire',
            'outdoor' => 'Interdit - Danger immédiat'
        ];
    }
}
