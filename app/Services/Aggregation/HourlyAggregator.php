<?php

namespace App\Services\Aggregation;

use App\Domain\Assets\Battery;
use App\Domain\Contracts\EnergyAssetInterface;
use App\Domain\Contracts\HourlyAggregatorInterface;
use App\Domain\Contracts\MarketPriceProviderInterface;
use App\Domain\Decision\DecisionContext;
use App\Services\AssetService;
use RuntimeException;

class HourlyAggregator implements HourlyAggregatorInterface
{
    public function __construct(
        private readonly AssetService $assetService,
        private readonly MarketPriceProviderInterface $marketPriceProvider,
    ) {
    }

    public function aggregate(int $hour, ?Battery $batterySnapshot = null): DecisionContext
    {
        $production = $this->sumHourlyOutput($this->assetService->listSolarPlants(), $hour)
            + $this->sumHourlyOutput($this->assetService->listWindPlants(), $hour);

        $consumption = $this->sumHourlyOutput($this->assetService->listConsumptionPoints(), $hour);

        $hourlyPrices = $this->marketPriceProvider->getHourlyPrices();
        $price = $hourlyPrices[$hour] ?? 0.0;

        $battery = $batterySnapshot ?? $this->assetService->getBattery();

        if ($battery === null) {
            throw new RuntimeException('No battery configured — cannot build a decision context.');
        }

        return new DecisionContext($hour, $production, $consumption, $price, $battery, $hourlyPrices);
    }

    /**
     * @param  EnergyAssetInterface[]  $assets
     */
    private function sumHourlyOutput(array $assets, int $hour): float
    {
        return array_sum(array_map(
            fn (EnergyAssetInterface $asset) => $asset->getHourlyOutput($hour),
            $assets,
        ));
    }
}
