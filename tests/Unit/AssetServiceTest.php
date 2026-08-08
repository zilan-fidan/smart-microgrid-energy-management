<?php

namespace Tests\Unit;

use App\Services\AssetService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryAssetRepository;
use Tests\Support\ThrowingProfileGeneratorResolver;

/**
 * Regression coverage: saveBattery() used to omit soh_percent from the
 * persisted payload, so JsonAssetRepository::save() (which replaces the
 * whole record) silently reset an already-worn battery back to 100% SOH
 * on every edit. The fix carries the existing record's SOH forward.
 */
class AssetServiceTest extends TestCase
{
    private function service(InMemoryAssetRepository $repository): AssetService
    {
        // saveBattery() never touches the profile generator — battery has no
        // hourly profile — so a resolver that throws on use is a safe fixture.
        return new AssetService($repository, new ThrowingProfileGeneratorResolver());
    }

    public function test_saving_a_new_battery_defaults_soh_to_100(): void
    {
        $repository = new InMemoryAssetRepository();
        $battery = $this->service($repository)->saveBattery('Ana Batarya', 100.0, 50.0, 10.0, 90.0, 0.9);

        $this->assertSame(100.0, $battery->getSohPercent());
    }

    public function test_editing_a_battery_preserves_its_existing_soh(): void
    {
        $repository = new InMemoryAssetRepository();
        $service = $this->service($repository);

        $battery = $service->saveBattery('Ana Batarya', 100.0, 50.0, 10.0, 90.0, 0.9);

        // Simulate wear having been persisted (e.g. by a future feature that
        // writes simulated degradation back) by writing a degraded SOH
        // directly through the repository, bypassing AssetService.
        $repository->save([
            'type' => 'battery',
            'id' => $battery->getId(),
            'name' => 'Ana Batarya',
            'capacity_kwh' => 100.0,
            'soc_percent' => 50.0,
            'min_soc' => 10.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.9,
            'soh_percent' => 97.5,
        ]);

        $this->assertSame(97.5, $service->getBattery()->getSohPercent());

        // Edit an unrelated field (capacity) through the normal CRUD path.
        $updated = $service->saveBattery('Ana Batarya', 120.0, 50.0, 10.0, 90.0, 0.9);

        $this->assertSame(120.0, $updated->getCapacityKwh());
        $this->assertSame(97.5, $updated->getSohPercent(), 'editing the battery must not reset its SOH');
        $this->assertSame(97.5, $service->getBattery()->getSohPercent());
    }
}
