<?php

namespace App\Services\Generators;

use App\Domain\Contracts\HourlyProfileGeneratorInterface;
use App\Domain\Contracts\ProfileGeneratorResolverInterface;
use InvalidArgumentException;

class ProfileGeneratorResolver implements ProfileGeneratorResolverInterface
{
    /** @var array<string, HourlyProfileGeneratorInterface> */
    private readonly array $generators;

    public function __construct(
        SolarProfileGenerator $solar,
        WindProfileGenerator $wind,
        ConsumptionProfileGenerator $consumption,
    ) {
        $this->generators = [
            'solar' => $solar,
            'wind' => $wind,
            'consumption' => $consumption,
        ];
    }

    public function resolve(string $assetType): HourlyProfileGeneratorInterface
    {
        return $this->generators[$assetType]
            ?? throw new InvalidArgumentException("No profile generator for asset type: {$assetType}");
    }
}
