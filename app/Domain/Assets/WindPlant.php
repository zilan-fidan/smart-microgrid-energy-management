<?php

namespace App\Domain\Assets;

use App\Domain\Contracts\EnergyAssetInterface;

class WindPlant implements EnergyAssetInterface
{
    /**
     * @param  float[]  $hourlyOutputKwh  24 values, index 0-23
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly float $capacityKw,
        private readonly array $hourlyOutputKwh = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            capacityKw: (float) $data['capacity_kw'],
            hourlyOutputKwh: $data['hourly_output_kwh'] ?? array_fill(0, 24, 0.0),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'wind',
            'id' => $this->id,
            'name' => $this->name,
            'capacity_kw' => $this->capacityKw,
            'hourly_output_kwh' => $this->hourlyOutputKwh,
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

    public function getCapacityKw(): float
    {
        return $this->capacityKw;
    }

    public function getHourlyOutput(int $hour): float
    {
        return $this->hourlyOutputKwh[$hour] ?? 0.0;
    }
}
