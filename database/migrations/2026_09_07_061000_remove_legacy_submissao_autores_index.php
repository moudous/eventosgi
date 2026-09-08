<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indice = 'submissao_autores_inscricao_submissao_id_ordem_index';
        if (Schema::hasIndex('submissao_autores', $indice)) {
            Schema::table('submissao_autores', fn (Blueprint $table) => $table->dropIndex($indice));
        }
    }

    public function down(): void
    {
        // Índice legado redundante; não deve ser recriado.
    }
};
