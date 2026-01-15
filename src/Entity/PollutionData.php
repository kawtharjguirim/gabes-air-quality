<?php

namespace App\Entity;

use App\Repository\PollutionDataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PollutionDataRepository::class)]
class PollutionData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'float')]
    private float $so2;

    #[ORM\Column(type: 'float')]
    private float $nh3;

    #[ORM\Column(type: 'float')]
    private float $pm25;

    #[ORM\Column(type: 'float')]
    private float $temperature;

    #[ORM\Column(type: 'float')]
    private float $humidity;

    #[ORM\Column(type: 'float')]
    private float $windSpeed;

    #[ORM\Column(type: 'float')]
    private float $windDirection;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $recordedAt;

    #[ORM\Column(length: 50)]
    private string $source;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $pressure = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $aqi = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSo2(): float
    {
        return $this->so2;
    }

    public function setSo2(float $so2): self
    {
        $this->so2 = $so2;
        return $this;
    }

    public function getNh3(): float
    {
        return $this->nh3;
    }

    public function setNh3(float $nh3): self
    {
        $this->nh3 = $nh3;
        return $this;
    }

    public function getPm25(): float
    {
        return $this->pm25;
    }

    public function setPm25(float $pm25): self
    {
        $this->pm25 = $pm25;
        return $this;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getHumidity(): float
    {
        return $this->humidity;
    }

    public function setHumidity(float $humidity): self
    {
        $this->humidity = $humidity;
        return $this;
    }

    public function getWindSpeed(): float
    {
        return $this->windSpeed;
    }

    public function setWindSpeed(float $windSpeed): self
    {
        $this->windSpeed = $windSpeed;
        return $this;
    }

    public function getWindDirection(): float
    {
        return $this->windDirection;
    }

    public function setWindDirection(float $windDirection): self
    {
        $this->windDirection = $windDirection;
        return $this;
    }

    public function getRecordedAt(): \DateTimeInterface
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeInterface $recordedAt): self
    {
        $this->recordedAt = $recordedAt;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getPressure(): ?float
    {
        return $this->pressure;
    }

    public function setPressure(?float $pressure): self
    {
        $this->pressure = $pressure;
        return $this;
    }

    public function getAqi(): ?float
    {
        return $this->aqi;
    }

    public function setAqi(?float $aqi): self
    {
        $this->aqi = $aqi;
        return $this;
    }
}
