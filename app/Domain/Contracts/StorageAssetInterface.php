<?php

namespace App\Domain\Contracts;

interface StorageAssetInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getSocPercent(): float;

    public function getCapacityKwh(): float;

    public function getMinSoc(): float;

    public function getMaxSoc(): float;

    public function getEfficiencyRate(): float;
}
