<?php

namespace App\Services;

use App\Domain\Assets\Battery;
use App\Domain\Assets\ConsumptionPoint;
use App\Domain\Assets\SolarPlant;
use App\Domain\Assets\WindPlant;
use App\Domain\Contracts\AssetRepositoryInterface;
use App\Domain\Contracts\ProfileGeneratorResolverInterface;

class AssetService
{
    public function __construct(
        private readonly AssetRepositoryInterface $repository,
        private readonly ProfileGeneratorResolverInterface $profileGenerators,
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
        $hourly = $id === null
            ? $this->generateHourlyValues('solar', $capacityKw)
            : $this->existingHourlyValues($id, 'hourly_output_kwh');

        $saved = $this->repository->save([
            'type' => 'solar',
            'id' => $id,
            'name' => $name,
            'capacity_kw' => $capacityKw,
            'hourly_output_kwh' => $hourly,
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
        $hourly = $id === null
            ? $this->generateHourlyValues('wind', $capacityKw)
            : $this->existingHourlyValues($id, 'hourly_output_kwh');

        $saved = $this->repository->save([
            'type' => 'wind',
            'id' => $id,
            'name' => $name,
            'capacity_kw' => $capacityKw,
            'hourly_output_kwh' => $hourly,
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
        $hourly = $id === null
            ? $this->generateHourlyValues('consumption', $averageDemandKwh)
            : $this->existingHourlyValues($id, 'hourly_demand_kwh');

        $saved = $this->repository->save([
            'type' => 'consumption',
            'id' => $id,
            'name' => $name,
            'average_demand_kwh' => $averageDemandKwh,
            'hourly_demand_kwh' => $hourly,
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
        float $replacementCostTl,
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
            'soh_percent' => $existing?->getSohPercent() ?? 100.0,
            'replacement_cost_tl' => $replacementCostTl,
        ]);

        return Battery::fromArray($saved);
    }

    /**
     * Rough starting suggestion for a new battery's replacement cost, shown
     * as a pre-filled (editable) form default: a flat TL/kWh nameplate
     * multiplier times the battery's capacity. Not a real market quote —
     * just a plausible order of magnitude the user can override.
     */
    public function suggestedReplacementCostTl(float $capacityKwh): float
    {
        return $capacityKwh * 100.0;
    }

    public function deleteBattery(): void
    {
        $battery = $this->getBattery();

        if ($battery !== null) {
            $this->repository->delete($battery->getId());
        }
    }

    /**
     * Auto-fill a new asset's hourly profile via its type's generator,
     * scaled to this specific asset's capacity/average value.
     */
    private function generateHourlyValues(string $type, float $scale): array
    {
        $shape = $this->profileGenerators->resolve($type)->generate();

        return array_map(fn (float $value) => round($value * $scale, 4), $shape);
    }

    /**
     * Preserve a previously stored hourly profile when editing an asset,
     * since the CRUD form only edits headline attributes.
     */
    private function existingHourlyValues(string $id, string $field): array
    {
        $record = $this->repository->find($id);

        return $record[$field] ?? array_fill(0, 24, 0.0);
    }
}
