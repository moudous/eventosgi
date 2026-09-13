<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->boolean('lista_reserva')->default(false)->index()->after('participante_email');
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropIndex(['lista_reserva']);
            $table->dropColumn('lista_reserva');
        });
    }
};
