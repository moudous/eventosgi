<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_presenca_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('atividade_id')->index();
            $table->string('hash', 64)->unique();
            $table->dateTime('inicio')->index();
            $table->dateTime('fim')->index();
            $table->integer('ajuste_minutos')->default(0);
            $table->unsignedInteger('cliques')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_presenca_links');
    }
};
