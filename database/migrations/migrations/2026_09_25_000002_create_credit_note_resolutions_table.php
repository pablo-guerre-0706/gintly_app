<?php

declare(strict_types=1);

use App\Enums\CreditNoteResolutionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-10 · Desglose normalizado y trazable del resarcimiento de una nota de crédito.
 *
 * Una NC puede resarcirse por MÁS DE UNA vía (p. ej. crédito parcialmente pagado: se reduce la
 * CxC por el saldo pendiente y el excedente ya pagado se reembolsa o queda como saldo a favor).
 * El único credit_notes.resolution_type no puede representar eso sin ambigüedad, así que cada vía
 * aplicada se registra como una fila aquí. Σ(amount) == credit_notes.total_amount (garantía de
 * servicio, atómica). La cabecera queda como 'mixto' cuando hay más de una vía.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_note_resolutions')) {
            Schema::create('credit_note_resolutions', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
                // Sesión de caja SOLO cuando la vía es reembolso en efectivo.
                $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();

                // Vía CONCRETA (nunca 'mixto').
                $table->enum('resolution_type', CreditNoteResolutionType::applicableValues())
                    ->index('idx_cnr_resolution');
                $table->decimal('amount', 14, 2); // bcmath escala 2.

                $table->timestamp('created_at')->nullable(); // Append-only: sin updated_at.

                $table->index('credit_note_id', 'idx_cnr_credit_note');
            });

            DB::statement(
                'ALTER TABLE `credit_note_resolutions` ADD CONSTRAINT `chk_cnr_amount_positive` CHECK (`amount` > 0)'
            );
        }

        // Extiende el enum de la cabecera para admitir 'mixto' (idempotente: solo si falta).
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['credit_notes', 'resolution_type']
        );

        if ($column !== null && ! str_contains((string) $column->COLUMN_TYPE, "'mixto'")) {
            $values = collect(CreditNoteResolutionType::values())
                ->map(static fn (string $v): string => "'".$v."'")
                ->implode(',');

            DB::statement("ALTER TABLE `credit_notes` MODIFY `resolution_type` ENUM({$values}) NOT NULL");
        }

        // BACKFILL histórico: genera EXACTAMENTE una resolución por cada nota de crédito preexistente
        // cuyo resolution_type NO sea 'mixto' y que aún no tenga desglose, copiando sus datos tal cual
        // (amount = total_amount). Idempotente por WHERE NOT EXISTS: reevaluarlo no duplica filas ni
        // convierte notas en mixtas; una base sin notas no inserta nada. El DDL de arriba ya hizo
        // implicit-commit en MySQL, así que la parte de DATOS se envuelve en su propia transacción.
        DB::transaction(static function (): void {
            DB::statement(
                'INSERT INTO `credit_note_resolutions` '
                . '(`business_id`, `credit_note_id`, `cash_session_id`, `resolution_type`, `amount`, `created_at`) '
                . 'SELECT cn.`business_id`, cn.`id`, cn.`cash_session_id`, cn.`resolution_type`, cn.`total_amount`, NOW() '
                . 'FROM `credit_notes` cn '
                . "WHERE cn.`resolution_type` <> 'mixto' "
                . 'AND NOT EXISTS ('
                . 'SELECT 1 FROM `credit_note_resolutions` r WHERE r.`credit_note_id` = cn.`id`'
                . ')'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_resolutions');

        // Revertir el enum de la cabecera a las tres vías concretas (si 'mixto' está presente).
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['credit_notes', 'resolution_type']
        );

        if ($column !== null && str_contains((string) $column->COLUMN_TYPE, "'mixto'")) {
            $values = collect(CreditNoteResolutionType::applicableValues())
                ->map(static fn (string $v): string => "'".$v."'")
                ->implode(',');

            DB::statement("ALTER TABLE `credit_notes` MODIFY `resolution_type` ENUM({$values}) NOT NULL");
        }
    }
};
