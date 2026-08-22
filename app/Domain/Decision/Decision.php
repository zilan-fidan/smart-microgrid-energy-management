<?php

namespace App\Domain\Decision;

class Decision
{
    /**
     * @param  string[]  $reasons  Human-readable justification, for explainability
     * @param  float  $lossKwh  Round-trip efficiency loss incurred by this decision
     *                          (0 for Sell/DrawFromGrid — no battery cycling involved)
     * @param  float  $curtailedSoldKwh  Surplus that a Store decision couldn't fit into
     *                                   the battery (headroom-limited) and sold to the
     *                                   market instead, in the same hour. Always 0 for
     *                                   every action other than Store.
     * @param  float  $expectedProfitTl  Estimated TL profit for a Store decision, based
     *                                   on the expected future sell price and round-trip
     *                                   efficiency (see DecisionContext::getExpectedSellPrice()).
     *                                   Always 0 for every action other than Store.
     */
    public function __construct(
        public readonly DecisionAction $action,
        public readonly float $amountKwh,
        public readonly array $reasons,
        public readonly float $resultingSocPercent,
        public readonly float $lossKwh = 0.0,
        public readonly float $curtailedSoldKwh = 0.0,
        public readonly float $expectedProfitTl = 0.0,
    ) {
    }
}
