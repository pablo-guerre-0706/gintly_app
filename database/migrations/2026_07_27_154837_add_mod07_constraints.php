<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        // Gravabilidad congelada en la línea.
        if (! Schema::hasColumn('sale_items', 'is_taxable')) {
            Schema::table('sale_items', function (Blueprint $table): void {
                $table->boolean('is_taxable')->default(false)->after('unit_cost');
            });
        }

        // recipe_snapshot, si el esquema base no lo trajo.
        if (! Schema::hasColumn('sale_items', 'recipe_snapshot')) {
            Schema::table('sale_items', function (Blueprint $table): void {
                $table->json('recipe_snapshot')->nullable()->after('line_total');
            });
        }

        // CHECK fiscales de sale_items si no existen.
        $this->addCheck('sale_items', 'chk_sale_item_price_non_negative', 'unit_price >= 0');
        $this->addCheck('sale_items', 'chk_sale_item_discount_non_negative', 'discount_amount >= 0');
        $this->addCheck('sale_items', 'chk_sale_item_line_total_non_negative', 'line_total >= 0');

        // CHECK fiscales de invoices si no existen.
        $this->addCheck('invoices', 'chk_invoice_paid_non_negative', 'paid_amount >= 0');
        $this->addCheck('invoices', 'chk_invoice_paid_not_exceed', 'paid_amount <= total');
        $this->addCheck(
            'invoices',
            'chk_invoice_void_coherence',
            "status <> 'anulada' OR voided_by IS NOT NULL"
        );

        // document_sequences si el esquema base no la creó.
        if (! Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->enum('document_type', ['invoice', 'credit_note']);
                $table->string('prefix', 10)->default('');
                $table->unsignedBigInteger('next_number')->default(1);
                $table->timestamps();

                $table->unique(['business_id', 'document_type'], 'uniq_document_sequence');
            });
        }
    }

    public function down(): void
    {
        // is_taxable y recipe_snapshot no se revierten: son datos congelados que
        // otros módulos (MOD-09, MOD-10) ya consumen. down() conservador.
        Schema::dropIfExists('document_sequences');
    }

    private function addCheck(string $table, string $constraint, string $expression): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();

        if (! $exists) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
        }
    }
};
