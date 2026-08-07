<?php

namespace App\Domain\Assets;

use App\Domain\Contracts\StorageAssetInterface;

class Battery implements StorageAssetInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly float $capacityKwh,
        private readonly float $socPercent,
        private readonly float $minSoc,
        private readonly float $maxSoc,
        private readonly float $efficiencyRate,
        private readonly float $sohPercent = 100.0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            capacityKwh: (float) $data['capacity_kwh'],
            socPercent: (float) $data['soc_percent'],
            minSoc: (float) $data['min_soc'],
            maxSoc: (float) $data['max_soc'],
            efficiencyRate: (float) $data['efficiency_rate'],
            sohPercent: (float) ($data['soh_percent'] ?? 100.0),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'battery',
            'id' => $this->id,
            'name' => $this->name,
            'capacity_kwh' => $this->capacityKwh,
            'soc_percent' => $this->socPercent,
            'min_soc' => $this->minSoc,
            'max_soc' => $this->maxSoc,
            'efficiency_rate' => $this->efficiencyRate,
            'soh_percent' => $this->sohPercent,
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSocPercent(): float
    {
        return $this->socPercent;
    }

    public function getCapacityKwh(): float
    {
        return $this->capacityKwh;
    }

    public function getMinSoc(): float
    {
        return $this->minSoc;
    }

    public function getMaxSoc(): float
    {
        return $this->maxSoc;
    }

    public function getEfficiencyRate(): float
    {
        return $this->efficiencyRate;
    }

    public function getSohPercent(): float
    {
        return $this->sohPercent;
    }
}
