<?php

namespace App\Services\Decision\Rules;

use App\Domain\Contracts\DecisionRuleInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;
use App\Services\Battery\DegradationCostCalculator;

class StoreSurplusRule implements DecisionRuleInterface
{
    public function __construct(
        private readonly DegradationCostCalculator $degradationCostCalculator,
    ) {
    }

    public function priority(): int
    {
        return 10;
    }

    /**
     * Breakeven test: does storing now actually pay off later, once BOTH
     * costs of cycling the battery are accounted for? Storing 1 raw kWh of
     * surplus returns `efficiency` kWh once discharged back out (the
     * round-trip energy loss), and also wears the cell by a small amount
     * (SimpleCycleDegradationRule's SOH loss, amortized to a TL/kWh cost by
     * DegradationCostCalculator). The net expected spread — future sell
     * price discounted by efficiency, minus the current price — has to
     * clear the wear cost too, not just be positive. A "profitable"
     * round-trip that only just covers the energy loss can still be a net
     * loss once you price in shaving cycles off the battery's life.
     */
    public function applies(DecisionContext $context): bool
    {
        if ($context->netSurplusOrDeficit() <= 0) {
            return false;
        }

        $battery = $context->battery;
        $expectedSpread = $context->getExpectedSellPrice() * $battery->getEfficiencyRate() - $context->priceKwh;
        $degradationCostPerKwh = $this->degradationCostCalculator->costPerKwh($battery);

        return $expectedSpread > $degradationCostPerKwh;
    }

    public function decide(DecisionContext $context): Decision
    {
        $battery = $context->battery;
        $surplusKwh = $context->netSurplusOrDeficit();

        // Round-trip efficiency is split evenly across charge/discharge legs.
        $chargeEfficiency = sqrt($battery->getEfficiencyRate());
        $headroomKwh = ($battery->getMaxSoc() - $battery->getSocPercent()) / 100 * $battery->getCapacityKwh();

        $wantedStoreKwh = $surplusKwh * $chargeEfficiency;
        $storedKwh = min($wantedStoreKwh, $headroomKwh);

        $socDelta = $battery->getCapacityKwh() > 0
            ? ($storedKwh / $battery->getCapacityKwh()) * 100
            : 0.0;
        $resultingSoc = min($battery->getMaxSoc(), $battery->getSocPercent() + $socDelta);

        $expectedSellPrice = $context->getExpectedSellPrice();
        $degradationCostPerKwh = $this->degradationCostCalculator->costPerKwh($battery);
        $expectedProfitTl = ($expectedSellPrice * $battery->getEfficiencyRate() - $context->priceKwh - $degradationCostPerKwh) * $storedKwh;

        $reasons = [
            'Üretim fazlası: '.round($surplusKwh, 2).' kWh',
            'Beklenen net kâr ≈ '.round($expectedProfitTl, 2).' TL'
                .' (beklenen satış fiyatı '.round($expectedSellPrice, 2).' TL/kWh,'
                .' alış fiyatı '.round($context->priceKwh, 2).' TL/kWh,'
                .' verim %'.round($battery->getEfficiencyRate() * 100).','
                .' '.round($degradationCostPerKwh * $storedKwh, 2).' TL aşınma maliyeti düşüldükten sonra)',
            'Batarya SOC sınırın altında, depolama mümkün',
        ];

        // Energy that entered the charging leg but was lost to inefficiency,
        // i.e. never became usable stored energy.
        $lossKwh = $chargeEfficiency > 0 ? $storedKwh * (1 / $chargeEfficiency - 1) : 0.0;

        // Raw surplus actually consumed by charging (inverse of the efficiency
        // applied above) — whatever surplus is left once the battery can't take
        // any more is curtailed onto the market this same hour instead of being
        // wasted.
        $rawConsumedForStorage = $chargeEfficiency > 0 ? $storedKwh / $chargeEfficiency : 0.0;
        $curtailedSoldKwh = max(0.0, $surplusKwh - $rawConsumedForStorage);

        if ($curtailedSoldKwh > 0.0) {
            $reasons[] = round($storedKwh, 2).' kWh depolandı, kalan '.round($curtailedSoldKwh, 2)
                .' kWh batarya kapasitesiyle sınırlı olduğu için piyasaya satıldı';
        }

        return new Decision(
            DecisionAction::Store,
            round($storedKwh, 4),
            $reasons,
            round($resultingSoc, 2),
            round($lossKwh, 4),
            round($curtailedSoldKwh, 4),
            round($expectedProfitTl, 4),
        );
    }
}
