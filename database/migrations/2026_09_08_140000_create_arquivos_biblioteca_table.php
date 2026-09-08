<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arquivos_biblioteca', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('arquivo', 80)->unique();
            $table->string('mime', 150);
            $table->string('formato', 15)->index();
            $table->string('tipo', 20)->index();
            $table->unsignedBigInteger('tamanho');
            $table->unsignedInteger('largura')->nullable();
            $table->unsignedInteger('altura')->nullable();
            $table->json('tags')->nullable();
            $table->string('categoria', 30)->nullable()->index();
            $table->unsignedBigInteger('enviado_por')->nullable()->index();
            $table->timestamps();

            $table->index('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivos_biblioteca');
    }
};
