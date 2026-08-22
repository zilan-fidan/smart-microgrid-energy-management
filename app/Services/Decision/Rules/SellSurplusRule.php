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
        // StoreSurplusRule (priority 10, evaluated before this rule) now
        // gates on an economic breakeven test rather than "price below
        // median" — so a surplus at a below-median price can legitimately
        // fail Store's test and reach this rule. Gating this rule on
        // isPriceHigh() would then let that surplus fall through to
        // DrawFromGridRule's fallback, which only handles deficits and
        // would silently drop it. Any surplus that reaches this rule (SOC
        // full is already intercepted by SocLimitGuardRule, economically
        // worthwhile storage by StoreSurplusRule) has nowhere else to go
        // but the market, regardless of price level.
        return $context->netSurplusOrDeficit() > 0;
    }

    public function decide(DecisionContext $context): Decision
    {
        $surplusKwh = $context->netSurplusOrDeficit();

        return new Decision(
            DecisionAction::Sell,
            round($surplusKwh, 4),
            [
                'Üretim fazlası: '.round($surplusKwh, 2).' kWh',
                $context->isPriceHigh()
                    ? 'Fiyat yüksek (medyan üstü)'
                    : 'Depolamanın beklenen kârı yok (round-trip kaybını karşılamıyor)',
                'Piyasaya satış tercih edildi',
            ],
            $context->battery->getSocPercent(),
        );
    }
}
