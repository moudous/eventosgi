<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convidados', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('sobrenome')->nullable();
            $table->string('titulacao')->nullable();
            $table->longText('curriculo')->nullable();
            $table->text('descricao')->nullable();
            $table->string('local')->nullable();
            $table->string('telefone_whatsapp', 30)->nullable();
            $table->json('redes_sociais')->nullable();
            $table->string('email', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convidados');
    }
};
