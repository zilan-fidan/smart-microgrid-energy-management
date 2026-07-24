<?php

namespace App\Services\Decision\Rules;

use App\Domain\Contracts\DecisionRuleInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;

class SellSurplusRule implements DecisionRuleInterface
{
    public function priority(): int
    {
        return 30;
    }

    public function applies(DecisionContext $context): bool
    {
        // The battery-full edge case is intercepted earlier by SocLimitGuardRule,
        // so by the time we get here a price-low surplus would already be caught
        // by StoreSurplusRule — this only fires for the price-high case.
        return $context->netSurplusOrDeficit() > 0 && $context->isPriceHigh();
    }

    public function decide(DecisionContext $context): Decision
    {
        $surplusKwh = $context->netSurplusOrDeficit();

        return new Decision(
            DecisionAction::Sell,
            round($surplusKwh, 4),
            [
                'Üretim fazlası: '.round($surplusKwh, 2).' kWh',
                'Fiyat yüksek (medyan üstü)',
                'Piyasaya satış tercih edildi',
            ],
            $context->battery->getSocPercent(),
        );
    }
}
