<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->boolean('mostrar_palavras_chave')->default(true);
            $table->unsignedSmallInteger('min_palavras_chave')->default(3);
            $table->unsignedSmallInteger('max_palavras_chave')->default(6);
        });
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->text('palavras_chave')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscritos_submissao_trabalhos', fn (Blueprint $table) => $table->dropColumn('palavras_chave'));
        Schema::table('submissoes', fn (Blueprint $table) => $table->dropColumn(['mostrar_palavras_chave', 'min_palavras_chave', 'max_palavras_chave']));
    }
};
