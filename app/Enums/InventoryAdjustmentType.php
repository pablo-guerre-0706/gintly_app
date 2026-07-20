<?php

namespace App\Enums;

enum InventoryAdjustmentType: string
{
    case Merma      = 'merma';
    case Sobrante   = 'sobrante';
    case Correccion = 'correccion';
}
