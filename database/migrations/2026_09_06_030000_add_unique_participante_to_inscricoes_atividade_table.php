<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            // Rede de segurança contra envios simultâneos: a checagem em
            // FormularioInscricaoService recusa antes, esta impede a corrida.
            // Inscrições anteriores à identificação têm participante_id nulo, e o MySQL
            // aceita vários nulos em índice único, então elas não conflitam entre si.
            $table->unique(['atividade_id', 'participante_id'], 'inscricoes_atividade_participante_unico');
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropUnique('inscricoes_atividade_participante_unico');
        });
    }
};
