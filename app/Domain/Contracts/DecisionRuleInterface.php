<?php

namespace App\Domain\Contracts;

use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionContext;

interface DecisionRuleInterface
{
    public function applies(DecisionContext $context): bool;

    public function decide(DecisionContext $context): Decision;

    /**
     * Lower number = evaluated earlier by the engine.
     */
    public function priority(): int;
}
