<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pix_cobrancas', function (Blueprint $table): void {
            // O status REMOVIDA_PELO_USUARIO_RECEBEDOR retornado pelo Sicoob
            // possui 31 caracteres e não cabia no limite original de 30.
            $table->string('status', 50)->default('CRIADA')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pix_cobrancas', function (Blueprint $table): void {
            $table->string('status', 30)->default('CRIADA')->change();
        });
    }
};
