<?php

namespace App\Entity;

use App\Repository\PredictionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PredictionRepository::class)]
class Prediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $pollutant;

    #[ORM\Column(type: 'float')]
    private float $predictedValue;

    #[ORM\Column]
    private int $hoursAhead;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $predictionFor;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $actualValue = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modelVersion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPollutant(): string
    {
        return $this->pollutant;
    }

    public function setPollutant(string $pollutant): self
    {
        $this->pollutant = $pollutant;
        return $this;
    }

    public function getPredictedValue(): float
    {
        return $this->predictedValue;
    }

    public function setPredictedValue(float $predictedValue): self
    {
        $this->predictedValue = $predictedValue;
        return $this;
    }

    public function getHoursAhead(): int
    {
        return $this->hoursAhead;
    }

    public function setHoursAhead(int $hoursAhead): self
    {
        $this->hoursAhead = $hoursAhead;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getPredictionFor(): \DateTimeInterface
    {
        return $this->predictionFor;
    }

    public function setPredictionFor(\DateTimeInterface $predictionFor): self
    {
        $this->predictionFor = $predictionFor;
        return $this;
    }

    public function getActualValue(): ?float
    {
        return $this->actualValue;
    }

    public function setActualValue(?float $actualValue): self
    {
        $this->actualValue = $actualValue;
        return $this;
    }

    public function getModelVersion(): ?string
    {
        return $this->modelVersion;
    }

    public function setModelVersion(?string $modelVersion): self
    {
        $this->modelVersion = $modelVersion;
        return $this;
    }
}
