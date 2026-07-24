<?php

namespace App\Services\Decision\Rules;

use App\Domain\Contracts\DecisionRuleInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;

/**
 * Fallback rule — always applies, must stay last in priority order so
 * every other rule gets a chance to handle the hour first.
 */
class DrawFromGridRule implements DecisionRuleInterface
{
    public function priority(): int
    {
        return 100;
    }

    public function applies(DecisionContext $context): bool
    {
        return true;
    }

    public function decide(DecisionContext $context): Decision
    {
        $deficitKwh = max(0.0, -$context->netSurplusOrDeficit());

        $reasons = $deficitKwh > 0
            ? [
                'Üretim açığı: '.round($deficitKwh, 2).' kWh',
                'Batarya yetersiz veya mevcut değil',
                'Şebekeden elektrik alınıyor',
            ]
            : ['Üretim ve tüketim dengede, şebeke alışverişi yok'];

        return new Decision(
            DecisionAction::DrawFromGrid,
            round($deficitKwh, 4),
            $reasons,
            $context->battery->getSocPercent(),
        );
    }
}
