<?php

namespace App\Services\Decision\Rules;

use App\Domain\Contracts\DecisionRuleInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;

class UseBatteryRule implements DecisionRuleInterface
{
    public function priority(): int
    {
        return 20;
    }

    public function applies(DecisionContext $context): bool
    {
        return $context->netSurplusOrDeficit() < 0
            && $context->battery->getSocPercent() > $context->battery->getMinSoc();
    }

    public function decide(DecisionContext $context): Decision
    {
        $battery = $context->battery;
        $deficitKwh = abs($context->netSurplusOrDeficit());

        // Round-trip efficiency is split evenly across charge/discharge legs.
        $dischargeEfficiency = sqrt($battery->getEfficiencyRate());
        $dischargeableStoredKwh = ($battery->getSocPercent() - $battery->getMinSoc()) / 100 * $battery->getCapacityKwh();
        $maxDeliverableKwh = $dischargeableStoredKwh * $dischargeEfficiency;

        $deliveredKwh = min($deficitKwh, $maxDeliverableKwh);
        $drawnFromBatteryKwh = $dischargeEfficiency > 0 ? $deliveredKwh / $dischargeEfficiency : 0.0;

        $socDelta = $battery->getCapacityKwh() > 0
            ? ($drawnFromBatteryKwh / $battery->getCapacityKwh()) * 100
            : 0.0;
        $resultingSoc = max($battery->getMinSoc(), $battery->getSocPercent() - $socDelta);

        $reasons = [
            'Üretim açığı: '.round($deficitKwh, 2).' kWh',
            'Batarya SOC yeterli (%'.round($battery->getSocPercent(), 1).' > min %'.$battery->getMinSoc().')',
        ];

        if ($context->isPriceHigh()) {
            $reasons[] = 'Fiyat yüksek, şebekeden almak yerine batarya tercih edildi';
        }

        if ($deliveredKwh < $deficitKwh) {
            $reasons[] = 'Açığın tamamı karşılanamadı, batarya kapasitesiyle sınırlı';
        }

        // Energy pulled out of the battery but lost to inefficiency before
        // reaching the load.
        $lossKwh = $drawnFromBatteryKwh - $deliveredKwh;

        return new Decision(DecisionAction::UseBattery, round($deliveredKwh, 4), $reasons, round($resultingSoc, 2), round($lossKwh, 4));
    }
}
