<?php
// app/Enums/StockTransferStatus.php
namespace App\Enums;

enum StockTransferStatus: string
{
    case Pendiente  = 'pendiente';
    case Completado = 'completado';
    case Cancelado  = 'cancelado';
}
