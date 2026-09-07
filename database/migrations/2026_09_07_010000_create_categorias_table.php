<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 255)->unique();
            $table->boolean('ativo')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('atividades', function (Blueprint $table): void {
            // Opcional: atividades ja cadastradas continuam sem categoria, e novas podem
            // ficar sem uma. restrictOnDelete respalda no banco a regra da aplicacao --
            // categoria com atividade vinculada nao e excluida.
            $table->foreignId('categoria_id')->nullable()->after('evento_id')
                ->constrained('categorias')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::dropIfExists('categorias');
    }
};
