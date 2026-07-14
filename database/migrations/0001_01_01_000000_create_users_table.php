<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();                                          // BIGINT UNSIGNED, PK, AI
            $table->unsignedBigInteger('business_id');            // NOT NULL. FK diferida
            $table->unsignedBigInteger('branch_id')->nullable();  // NULL. FK diferida
            $table->string('name', 150);
            $table->string('email', 180);                         // SIN ->unique() global
            $table->string('password');                           // hash bcrypt/argon (255)
            $table->boolean('is_active')->default(true);          // desactivación lógica de acceso
            $table->timestamp('last_login_at')->nullable();       // para detectar omisiones
            $table->rememberToken();                              // VARCHAR(100) estándar Laravel
            $table->timestamps();
            $table->softDeletes();

            // Unicidad correcta: el email es único POR negocio, no globalmente
            $table->unique(['business_id', 'email']);

            // Índice para las consultas soft-delete (WHERE deleted_at IS NULL)
            $table->index('deleted_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
