<?php

namespace Tests\Support;

use App\Domain\Contracts\HourlyProfileGeneratorInterface;
use App\Domain\Contracts\ProfileGeneratorResolverInterface;
use RuntimeException;

/**
 * Fails loudly if AssetService ever tries to auto-generate an hourly
 * profile in a test that seeds all data directly — a signal that the
 * test accidentally exercised the create-new-asset path instead of
 * reading pre-seeded fixtures.
 */
class ThrowingProfileGeneratorResolver implements ProfileGeneratorResolverInterface
{
    public function resolve(string $assetType): HourlyProfileGeneratorInterface
    {
        throw new RuntimeException("Unexpected profile generator resolution for asset type: {$assetType}");
    }
}
