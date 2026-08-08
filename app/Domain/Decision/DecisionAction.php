<?php

namespace App\Domain\Decision;

enum DecisionAction: string
{
    case Store = 'store';
    case Sell = 'sell';
    case UseBattery = 'use_battery';
    case DrawFromGrid = 'draw_from_grid';
}
