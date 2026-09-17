<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropUnique('inscricoes_atividade_participante_unico');
            // NULL permite manter vários cancelamentos no histórico; somente a inscrição
            // ativa usa 1 e, portanto, participa efetivamente da restrição única.
            $table->boolean('ativa')->nullable()->default(true)->after('lista_reserva')->index();
            $table->timestamp('cancelada_em')->nullable()->after('ativa')->index();
            $table->string('cancelamento_motivo', 255)->nullable()->after('cancelada_em');
            $table->unique(['atividade_id', 'participante_id', 'ativa'], 'inscricoes_atividade_participante_ativo_unico');
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropUnique('inscricoes_atividade_participante_ativo_unico');
            $table->dropColumn(['ativa', 'cancelada_em', 'cancelamento_motivo']);
            $table->unique(['atividade_id', 'participante_id'], 'inscricoes_atividade_participante_unico');
        });
    }
};
