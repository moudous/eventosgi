<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->boolean('presente')->default(false)->index();
            $table->dateTime('data_presenca')->nullable();
            $table->unsignedBigInteger('presenca_validada_por')->nullable()->index();
            $table->string('codigo_qr', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropColumn(['presente', 'data_presenca', 'presenca_validada_por', 'codigo_qr']);
        });
    }
};
