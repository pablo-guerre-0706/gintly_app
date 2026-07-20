<?php
// app/Enums/SupplierStatus.php
namespace App\Enums;

enum SupplierStatus: string
{
    case Pendiente  = 'pendiente';
    case Aprobado   = 'aprobado';
    case Suspendido = 'suspendido';

    /** RF-04-02: solo un proveedor aprobado admite órdenes de compra. */
    public function canReceiveOrders(): bool
    {
        return $this === self::Aprobado;
    }
}