<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->boolean('mostrar_categoria_trabalho')->default(true);
        });
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->string('categoria_trabalho', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscritos_submissao_trabalhos', fn (Blueprint $table) => $table->dropColumn('categoria_trabalho'));
        Schema::table('submissoes', fn (Blueprint $table) => $table->dropColumn('mostrar_categoria_trabalho'));
    }
};
