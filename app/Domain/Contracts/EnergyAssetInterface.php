<?php

namespace App\Domain\Contracts;

interface EnergyAssetInterface
{
    public function getId(): string;

    public function getName(): string;

    /**
     * Energy output (kWh) for a given hour of the day (0-23).
     */
    public function getHourlyOutput(int $hour): float;
}
