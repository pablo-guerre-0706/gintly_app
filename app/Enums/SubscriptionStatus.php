<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado COMERCIAL de la suscripción SaaS (distinto de BusinessStatus, que es suspensión administrativa).
 * El acceso operativo exige un estado que conceda acceso Y una vigencia (paid_until) no vencida; el estado
 * por sí solo no basta (lo valida PlanSubscription::grantsAccessNow()).
 */
enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment'; // contratación iniciada, sin pago verificado
    case Incomplete     = 'incomplete';      // checkout abierto, confirmación pendiente
    case Active         = 'active';          // pago verificado y vigente
    case PastDue        = 'past_due';         // renovación fallida; sin extender vigencia
    case Canceled       = 'canceled';        // cancelada (conserva acceso hasta paid_until)
    case Expired        = 'expired';         // vigencia vencida → sin acceso

    /** Estados cuyo acceso depende de que paid_until siga vigente (no terminales operativos). */
    public function mayGrantAccess(): bool
    {
        return match ($this) {
            self::Active, self::Canceled, self::PastDue => true,
            self::PendingPayment, self::Incomplete, self::Expired => false,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Expired;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
