<?php

namespace App\Domain\Assets;

use App\Domain\Contracts\EnergyAssetInterface;

class ConsumptionPoint implements EnergyAssetInterface
{
    /**
     * @param  float[]  $hourlyDemandKwh  24 values, index 0-23
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly float $averageDemandKwh,
        private readonly array $hourlyDemandKwh = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $average = (float) $data['average_demand_kwh'];

        return new self(
            id: $data['id'],
            name: $data['name'],
            averageDemandKwh: $average,
            hourlyDemandKwh: $data['hourly_demand_kwh'] ?? array_fill(0, 24, $average),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'consumption',
            'id' => $this->id,
            'name' => $this->name,
            'average_demand_kwh' => $this->averageDemandKwh,
            'hourly_demand_kwh' => $this->hourlyDemandKwh,
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

    public function getAverageDemandKwh(): float
    {
        return $this->averageDemandKwh;
    }

    /**
     * Demand is modelled as output here so consumption points share the
     * same contract as producing assets — the sign/meaning is contextual
     * to whoever consumes it (the decision engine treats it as demand).
     */
    public function getHourlyOutput(int $hour): float
    {
        return $this->hourlyDemandKwh[$hour] ?? $this->averageDemandKwh;
    }
}
