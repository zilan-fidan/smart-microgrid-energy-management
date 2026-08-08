<?php

namespace App\Domain\Contracts;

interface HourlyProfileGeneratorInterface
{
    /**
     * @return float[] 24 values indexed 0-23.
     */
    public function generate(): array;
}
