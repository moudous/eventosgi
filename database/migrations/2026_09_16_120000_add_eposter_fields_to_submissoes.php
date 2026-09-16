<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->dateTime('eposter_data_inicio')->nullable()->after('data_fim')->index();
            $table->dateTime('eposter_data_fim')->nullable()->after('eposter_data_inicio')->index();
        });

        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->string('eposter_arquivo')->nullable()->after('situacao');
            $table->string('eposter_nome_original')->nullable()->after('eposter_arquivo');
            $table->dateTime('eposter_enviado_em')->nullable()->after('eposter_nome_original');
        });
    }

    public function down(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->dropColumn(['eposter_arquivo', 'eposter_nome_original', 'eposter_enviado_em']);
        });

        Schema::table('submissoes', function (Blueprint $table): void {
            $table->dropColumn(['eposter_data_inicio', 'eposter_data_fim']);
        });
    }
};
