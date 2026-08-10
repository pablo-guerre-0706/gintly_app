<?php

declare(strict_types=1);

namespace App\Enums;

enum AnomalyStatus: string
{
    case Detectada  = 'detectada';
    case Notificada = 'notificada';
    case EnRevision = 'en_revision';
    case Justificada = 'justificada';
    case Resuelta   = 'resuelta';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    /** Estados ACTIVOS: presentes en active_dedupe_key (bloquean duplicados). */
    public function isActive(): bool
    {
        return in_array($this, [self::Detectada, self::Notificada, self::EnRevision], true);
    }

    /** ¿Puede justificarse (RF-11-07)? Solo desde un estado activo. */
    public function canJustify(): bool
    {
        return $this->isActive();
    }

    /** ¿Puede resolverse (ROL-01)? Desde cualquier estado no resuelto. */
    public function canResolve(): bool
    {
        return $this !== self::Resuelta;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Justificada, self::Resuelta], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Detectada   => 'Detectada',
            self::Notificada  => 'Notificada',
            self::EnRevision  => 'En revisión',
            self::Justificada => 'Justificada',
            self::Resuelta    => 'Resuelta',
        };
    }
}
