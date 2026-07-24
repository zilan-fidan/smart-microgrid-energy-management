<?php

namespace App\Domain\Contracts;

use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionContext;

interface DecisionEngineInterface
{
    public function decide(DecisionContext $context): Decision;
}
