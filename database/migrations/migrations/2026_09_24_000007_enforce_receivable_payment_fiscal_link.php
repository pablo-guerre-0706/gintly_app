<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-08 · Integridad de los DOS libros de pagos (microcierre).
 *
 * Convierte la relación 1:1 invoice_payments ↔ receivable_payments en una garantía
 * del MOTOR, no solo de Eloquent:
 *   (1) BACKFILL determinista y seguro de datos previos (sin migrate:fresh):
 *       - enlaza cada abono huérfano a su invoice_payment equivalente cuando el
 *         emparejamiento es inequívoco;
 *       - reconstruye el invoice_payment faltante SOLO cuando es determinista desde
 *         el abono y su factura;
 *       - materializa el asiento de cartera de los pagos INICIALES de facturas a
 *         crédito que aún no lo tengan, SIN alterar acumulados;
 *       - ante cualquier ambigüedad, ABORTA con un mensaje controlado (no inventa enlaces).
 *   (2) UNIQUE(invoice_payment_id): impide que dos abonos apunten al mismo asiento fiscal.
 *   (3) invoice_payment_id NOT NULL: todo abono (inicial o posterior) DEBE tener respaldo fiscal.
 *
 * La FK ya es ON DELETE RESTRICT (migración 000006). Este cambio es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Requiere que exista la columna (migración 000006). Si no, no hay nada que endurecer.
        if (! Schema::hasColumn('receivable_payments', 'invoice_payment_id')) {
            return;
        }

        // (1) BACKFILL de datos previos, atómico: si algo es ambiguo, se revierte por completo.
        DB::transaction(function (): void {
            $this->linkOrReconstructOrphanPayments();
            $this->materializeMissingInitialEntries();
        });

        // (1b) Verificación previa al endurecimiento: nada nulo ni duplicado.
        $this->assertLedgerIsClean();

        // (2) UNIQUE(invoice_payment_id) — 1:1 garantizado por el motor.
        if (! $this->indexExists('uniq_rp_invoice_payment')) {
            DB::statement('ALTER TABLE `receivable_payments` ADD UNIQUE `uniq_rp_invoice_payment` (`invoice_payment_id`)');
        }

        // (3) NOT NULL definitivo. MODIFY conserva la FK RESTRICT existente.
        if ($this->columnIsNullable()) {
            DB::statement('ALTER TABLE `receivable_payments` MODIFY `invoice_payment_id` BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('receivable_payments', 'invoice_payment_id')) {
            return;
        }

        // Revertir NOT NULL → NULL y quitar el UNIQUE, preservando la FK RESTRICT.
        // Se recrea la FK para no quedar sin índice de soporte al soltar el UNIQUE.
        DB::statement('ALTER TABLE `receivable_payments` DROP FOREIGN KEY `receivable_payments_invoice_payment_id_foreign`');

        if ($this->indexExists('uniq_rp_invoice_payment')) {
            DB::statement('ALTER TABLE `receivable_payments` DROP INDEX `uniq_rp_invoice_payment`');
        }

        if (! $this->columnIsNullable()) {
            DB::statement('ALTER TABLE `receivable_payments` MODIFY `invoice_payment_id` BIGINT UNSIGNED NULL');
        }

        DB::statement(
            'ALTER TABLE `receivable_payments` '
            . 'ADD CONSTRAINT `receivable_payments_invoice_payment_id_foreign` '
            . 'FOREIGN KEY (`invoice_payment_id`) REFERENCES `invoice_payments` (`id`) ON DELETE RESTRICT'
        );
    }

    /**
     * Enlaza cada abono sin invoice_payment_id a su asiento fiscal equivalente. Si no
     * existe, lo reconstruye de forma determinista desde el propio abono y su factura.
     * Aborta ante ambigüedad irresoluble.
     */
    private function linkOrReconstructOrphanPayments(): void
    {
        $orphans = DB::table('receivable_payments as rp')
            ->join('accounts_receivables as ar', 'ar.id', '=', 'rp.accounts_receivable_id')
            ->whereNull('rp.invoice_payment_id')
            ->orderBy('rp.id')
            ->get([
                'rp.id', 'rp.business_id', 'rp.amount', 'rp.payment_method', 'rp.cash_session_id',
                'rp.user_id', 'rp.reference', 'rp.paid_at', 'rp.created_at', 'ar.invoice_id',
            ]);

        foreach ($orphans as $rp) {
            // invoice_payments de la misma factura AÚN no referenciados por ningún abono.
            $candidates = DB::table('invoice_payments as ip')
                ->where('ip.invoice_id', $rp->invoice_id)
                ->where('ip.amount', $rp->amount)
                ->where('ip.payment_method', $rp->payment_method)
                ->whereNotExists(function ($q): void {
                    $q->selectRaw('1')->from('receivable_payments as x')
                        ->whereColumn('x.invoice_payment_id', 'ip.id');
                })
                ->orderBy('ip.id')
                ->get(['ip.id', 'ip.cash_session_id', 'ip.reference', 'ip.paid_at']);

            if ($candidates->count() === 1) {
                $ipId = (int) $candidates->first()->id;
            } elseif ($candidates->isEmpty()) {
                // Reconstrucción determinista del asiento fiscal faltante desde el abono.
                $ipId = (int) DB::table('invoice_payments')->insertGetId([
                    'business_id'     => $rp->business_id,
                    'invoice_id'      => $rp->invoice_id,
                    'cash_session_id' => $rp->cash_session_id,
                    'user_id'         => $rp->user_id,
                    'payment_method'  => $rp->payment_method,
                    'amount'          => $rp->amount,
                    'reference'       => $rp->reference,
                    'paid_at'         => $rp->paid_at,
                    'created_at'      => $rp->created_at ?? now(),
                ]);
            } else {
                // Varios equivalentes: intentar desambiguar por sesión + referencia + fecha.
                $narrowed = $candidates->filter(fn ($c): bool =>
                    (string) $c->cash_session_id === (string) $rp->cash_session_id
                    && (string) $c->reference === (string) $rp->reference
                    && (string) $c->paid_at === (string) $rp->paid_at);

                if ($narrowed->count() === 1) {
                    $ipId = (int) $narrowed->first()->id;
                } else {
                    throw new RuntimeException(
                        "Backfill MOD-08 abortado: enlace AMBIGUO para receivable_payment #{$rp->id} "
                        . "(factura #{$rp->invoice_id}); {$candidates->count()} invoice_payments equivalentes. "
                        . 'No se inventa el enlace: reconcilie manualmente antes de reintentar.'
                    );
                }
            }

            // Query builder (no el modelo): la inmutabilidad de Eloquent no aplica al backfill.
            DB::table('receivable_payments')->where('id', $rp->id)->update(['invoice_payment_id' => $ipId]);
        }
    }

    /**
     * Materializa el receivable_payment de los pagos INICIALES de facturas a crédito
     * que carezcan de él, copiando el asiento fiscal y SIN alterar acumulados.
     */
    private function materializeMissingInitialEntries(): void
    {
        $missing = DB::table('invoice_payments as ip')
            ->join('invoices as i', 'i.id', '=', 'ip.invoice_id')
            ->join('accounts_receivables as ar', 'ar.invoice_id', '=', 'i.id')
            ->where('i.payment_type', 'credito')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from('receivable_payments as rp')
                    ->whereColumn('rp.invoice_payment_id', 'ip.id');
            })
            ->orderBy('ip.id')
            ->get([
                'ip.id as ip_id', 'ip.business_id', 'ip.cash_session_id', 'ip.user_id',
                'ip.payment_method', 'ip.amount', 'ip.reference', 'ip.paid_at', 'ip.created_at',
                'ar.id as ar_id',
            ]);

        foreach ($missing as $m) {
            DB::table('receivable_payments')->insert([
                'business_id'            => $m->business_id,
                'accounts_receivable_id' => $m->ar_id,
                'invoice_payment_id'     => $m->ip_id,
                'cash_session_id'        => $m->cash_session_id,
                'user_id'                => $m->user_id,
                'payment_method'         => $m->payment_method,
                'amount'                 => $m->amount,
                'reference'              => $m->reference,
                'paid_at'                => $m->paid_at,
                'created_at'             => $m->created_at ?? now(),
            ]);
        }
    }

    /** Garantiza 0 nulos y 0 enlaces duplicados antes de endurecer el esquema. */
    private function assertLedgerIsClean(): void
    {
        $nulls = DB::table('receivable_payments')->whereNull('invoice_payment_id')->count();
        if ($nulls > 0) {
            throw new RuntimeException(
                "Backfill MOD-08 abortado: quedan {$nulls} receivable_payments sin invoice_payment_id."
            );
        }

        $dups = DB::table('receivable_payments')
            ->select('invoice_payment_id')
            ->groupBy('invoice_payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($dups > 0) {
            throw new RuntimeException(
                "Backfill MOD-08 abortado: {$dups} invoice_payment_id referenciados por más de un abono."
            );
        }
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'receivable_payments')
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    private function columnIsNullable(): bool
    {
        $col = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['receivable_payments', 'invoice_payment_id']
        );

        return $col !== null && strtoupper((string) $col->IS_NULLABLE) === 'YES';
    }
};
