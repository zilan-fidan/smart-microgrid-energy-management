<?php

namespace App\Services\Decision\Rules;

use App\Domain\Contracts\DecisionRuleInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;

/**
 * Safety net, evaluated before every economic rule. In normal operation
 * (SOC within bounds) it steps aside and lets Store/Sell/UseBattery decide
 * — it only intervenes in the exact two scenarios where the "natural"
 * decision would push SOC past its configured limits, redirecting to the
 * nearest safe alternative (sell instead of overfilling, draw from grid
 * instead of over-draining) so no other rule ever has to violate SOC.
 */
class SocLimitGuardRule implements DecisionRuleInterface
{
    public function priority(): int
    {
        return 0;
    }

    public function applies(DecisionContext $context): bool
    {
        return $this->wouldOverflowIfStoring($context) || $this->wouldUnderflowIfDischarging($context);
    }

    public function decide(DecisionContext $context): Decision
    {
        if ($this->wouldOverflowIfStoring($context)) {
            return new Decision(
                DecisionAction::Sell,
                round($context->netSurplusOrDeficit(), 4),
                [
                    'Üretim fazlası: '.round($context->netSurplusOrDeficit(), 2).' kWh',
                    'Batarya SOC üst sınırda (%'.$context->battery->getMaxSoc().'), depolama engellendi',
                    'Fazla enerji piyasaya satılıyor',
                ],
                $context->battery->getSocPercent(),
            );
        }

        return new Decision(
            DecisionAction::DrawFromGrid,
            round(abs($context->netSurplusOrDeficit()), 4),
            [
                'Üretim açığı: '.round(abs($context->netSurplusOrDeficit()), 2).' kWh',
                'Batarya SOC alt sınırda (%'.$context->battery->getMinSoc().'), bataryadan kullanım engellendi',
                'Şebekeden elektrik alınıyor',
            ],
            $context->battery->getSocPercent(),
        );
    }

    private function wouldOverflowIfStoring(DecisionContext $context): bool
    {
        return $context->netSurplusOrDeficit() > 0
            && $context->battery->getSocPercent() >= $context->battery->getMaxSoc();
    }

    private function wouldUnderflowIfDischarging(DecisionContext $context): bool
    {
        return $context->netSurplusOrDeficit() < 0
            && $context->battery->getSocPercent() <= $context->battery->getMinSoc();
    }
}
