<?php
// app/Enums/AccountPayableStatus.php
namespace App\Enums;

enum AccountPayableStatus: string
{
    case Pendiente = 'pendiente';
    case Congelada = 'congelada';   // 3-Way Match con discrepancia (RF-04-04)
    case Parcial   = 'parcial';
    case Pagada    = 'pagada';

    public function isBlocked(): bool
    {
        return $this === self::Congelada;
    }
}
