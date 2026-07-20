<?php
// app/Enums/PhysicalCountStatus.php
namespace App\Enums;

enum PhysicalCountStatus: string
{
    case Abierto     = 'abierto';
    case Justificado = 'justificado';
    case Ajustado    = 'ajustado';
}
