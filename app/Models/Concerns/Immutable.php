<?php

namespace App\Models\Concerns;

use App\Exceptions\ImmutableRecordException;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * 
 * @method static void updating(\Closure $callback)
 * @method static void deleting(\Closure $callback)
 */
trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(function (): void {
            throw new ImmutableRecordException;
        });

        static::deleting(function (): void {
            throw new ImmutableRecordException;
        });
    }
}
