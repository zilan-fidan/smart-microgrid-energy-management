<?php

namespace App\Services;

use App\Domain\Assets\Battery;
use App\Domain\Assets\ConsumptionPoint;
use App\Domain\Assets\SolarPlant;
use App\Domain\Assets\WindPlant;
use App\Domain\Contracts\AssetRepositoryInterface;

class AssetService
{
    public function __construct(
        private readonly AssetRepositoryInterface $repository,
    ) {
    }

    /**
     * @return SolarPlant[]
     */
    public function listSolarPlants(): array
    {
        return array_map(
            fn (array $record) => SolarPlant::fromArray($record),
            $this->repository->findAll('solar'),
        );
    }

    public function saveSolarPlant(?string $id, string $name, float $capacityKw): SolarPlant
    {
        $saved = $this->repository->save([
            'type' => 'solar',
            'id' => $id,
            'name' => $name,
            'capacity_kw' => $capacityKw,
            'hourly_output_kwh' => $this->existingHourlyValues('solar', $id, 'hourly_output_kwh'),
        ]);

        return SolarPlant::fromArray($saved);
    }

    public function deleteSolarPlant(string $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * @return WindPlant[]
     */
    public function listWindPlants(): array
    {
        return array_map(
            fn (array $record) => WindPlant::fromArray($record),
            $this->repository->findAll('wind'),
        );
    }

    public function saveWindPlant(?string $id, string $name, float $capacityKw): WindPlant
    {
        $saved = $this->repository->save([
            'type' => 'wind',
            'id' => $id,
            'name' => $name,
            'capacity_kw' => $capacityKw,
            'hourly_output_kwh' => $this->existingHourlyValues('wind', $id, 'hourly_output_kwh'),
        ]);

        return WindPlant::fromArray($saved);
    }

    public function deleteWindPlant(string $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * @return ConsumptionPoint[]
     */
    public function listConsumptionPoints(): array
    {
        return array_map(
            fn (array $record) => ConsumptionPoint::fromArray($record),
            $this->repository->findAll('consumption'),
        );
    }

    public function saveConsumptionPoint(?string $id, string $name, float $averageDemandKwh): ConsumptionPoint
    {
        $saved = $this->repository->save([
            'type' => 'consumption',
            'id' => $id,
            'name' => $name,
            'average_demand_kwh' => $averageDemandKwh,
            'hourly_demand_kwh' => array_fill(0, 24, $averageDemandKwh),
        ]);

        return ConsumptionPoint::fromArray($saved);
    }

    public function deleteConsumptionPoint(string $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * Only one battery is supported system-wide, so this returns the
     * single existing record (if any) instead of a collection.
     */
    public function getBattery(): ?Battery
    {
        $records = $this->repository->findAll('battery');

        return $records === [] ? null : Battery::fromArray($records[0]);
    }

    public function saveBattery(
        string $name,
        float $capacityKwh,
        float $socPercent,
        float $minSoc,
        float $maxSoc,
        float $efficiencyRate,
    ): Battery {
        $existing = $this->getBattery();

        $saved = $this->repository->save([
            'type' => 'battery',
            'id' => $existing?->getId(),
            'name' => $name,
            'capacity_kwh' => $capacityKwh,
            'soc_percent' => $socPercent,
            'min_soc' => $minSoc,
            'max_soc' => $maxSoc,
            'efficiency_rate' => $efficiencyRate,
        ]);

        return Battery::fromArray($saved);
    }

    public function deleteBattery(): void
    {
        $battery = $this->getBattery();

        if ($battery !== null) {
            $this->repository->delete($battery->getId());
        }
    }

    /**
     * Preserve a previously stored hourly profile when editing an asset,
     * since the CRUD form only edits headline attributes (profiles are
     * populated by the mock data generator in a later phase).
     */
    private function existingHourlyValues(string $type, ?string $id, string $field): array
    {
        if ($id === null) {
            return array_fill(0, 24, 0.0);
        }

        $record = $this->repository->find($id);

        return $record[$field] ?? array_fill(0, 24, 0.0);
    }
}
