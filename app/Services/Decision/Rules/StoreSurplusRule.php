<?php

namespace App\Services\Decision\Rules;

use App\Domain\Contracts\DecisionRuleInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;

class StoreSurplusRule implements DecisionRuleInterface
{
    public function priority(): int
    {
        return 10;
    }

    /**
     * Breakeven test: does storing now actually pay off later? Storing 1 raw
     * kWh of surplus returns `efficiency` kWh once discharged back out (the
     * round-trip loss is unavoidable), so it's only worth it when the
     * expected future sell price, discounted by that efficiency, still
     * beats what the surplus is worth right now (the current price). If it
     * doesn't, storing is a guaranteed loss no matter how "low" the current
     * price looks against the day's median — the old median-based check
     * couldn't tell the two apart, this one can.
     */
    public function applies(DecisionContext $context): bool
    {
        if ($context->netSurplusOrDeficit() <= 0) {
            return false;
        }

        $breakevenSellPrice = $context->getExpectedSellPrice() * $context->battery->getEfficiencyRate();

        return $breakevenSellPrice > $context->priceKwh;
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
        $expectedProfitTl = ($expectedSellPrice * $battery->getEfficiencyRate() - $context->priceKwh) * $storedKwh;

        $reasons = [
            'Üretim fazlası: '.round($surplusKwh, 2).' kWh',
            'Bu işlemin beklenen kârı ≈ '.round($expectedProfitTl, 2).' TL'
                .' (beklenen satış fiyatı '.round($expectedSellPrice, 2).' TL/kWh,'
                .' alış fiyatı '.round($context->priceKwh, 2).' TL/kWh,'
                .' verim %'.round($battery->getEfficiencyRate() * 100).')',
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
