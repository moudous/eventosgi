<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates_pagina', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 150);
            // Pasta em storage/app/private/templates-pagina, derivada do nome.
            $table->string('pasta', 100)->unique();
            $table->string('descricao', 500)->nullable();
            $table->string('versao', 20)->nullable();
            // Variaveis que o template declara no manifesto, para a tela de edicao da
            // pagina saber quais campos oferecer a quem monta o evento.
            $table->json('variaveis')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->unsignedBigInteger('importado_por')->nullable();
            $table->timestamps();
        });

        Schema::table('eventos', function (Blueprint $table): void {
            // restrictOnDelete respalda no banco a regra da aplicacao: template em uso
            // por algum evento nao e removido.
            $table->foreignId('template_pagina_id')->nullable()->after('ativo')
                ->constrained('templates_pagina')->restrictOnDelete();
            $table->json('pagina_variaveis')->nullable()->after('template_pagina_id');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table): void {
            $table->dropForeign(['template_pagina_id']);
            $table->dropColumn(['template_pagina_id', 'pagina_variaveis']);
        });

        Schema::dropIfExists('templates_pagina');
    }
};
