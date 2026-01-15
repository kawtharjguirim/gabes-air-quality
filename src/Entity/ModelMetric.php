<?php

namespace App\Entity;

use App\Repository\ModelMetricRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModelMetricRepository::class)]
class ModelMetric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $modelName;

    #[ORM\Column(type: 'float')]
    private float $rmse;

    #[ORM\Column(type: 'float')]
    private float $mae;

    #[ORM\Column(type: 'float')]
    private float $r2;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $trainedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function setModelName(string $modelName): self
    {
        $this->modelName = $modelName;
        return $this;
    }

    public function getRmse(): float
    {
        return $this->rmse;
    }

    public function setRmse(float $rmse): self
    {
        $this->rmse = $rmse;
        return $this;
    }

    public function getMae(): float
    {
        return $this->mae;
    }

    public function setMae(float $mae): self
    {
        $this->mae = $mae;
        return $this;
    }

    public function getR2(): float
    {
        return $this->r2;
    }

    public function setR2(float $r2): self
    {
        $this->r2 = $r2;
        return $this;
    }

    public function getTrainedAt(): \DateTimeInterface
    {
        return $this->trainedAt;
    }

    public function setTrainedAt(\DateTimeInterface $trainedAt): self
    {
        $this->trainedAt = $trainedAt;
        return $this;
    }
}
