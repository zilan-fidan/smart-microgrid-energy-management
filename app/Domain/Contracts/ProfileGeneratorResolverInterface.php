<?php

namespace App\Domain\Contracts;

interface ProfileGeneratorResolverInterface
{
    /**
     * @param  string  $assetType  'solar'|'wind'|'consumption'
     */
    public function resolve(string $assetType): HourlyProfileGeneratorInterface;
}
