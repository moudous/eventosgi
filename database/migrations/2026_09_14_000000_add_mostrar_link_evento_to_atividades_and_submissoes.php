<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['atividades', 'submissoes'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->boolean('mostrar_link_evento')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['atividades', 'submissoes'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn('mostrar_link_evento');
            });
        }
    }
};
