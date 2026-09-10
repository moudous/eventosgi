<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atividade_convidado', function (Blueprint $table) {
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->foreignId('convidado_id')->constrained('convidados')->cascadeOnDelete();
            $table->unsignedInteger('ordem');
            $table->primary(['atividade_id', 'convidado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividade_convidado');
    }
};
