<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->boolean('mostrar_apresentacao')->default(true);
            $table->boolean('mostrar_aprovacao_comite_etica')->default(true);
            $table->boolean('mostrar_apoio_financeiro')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->dropColumn(['mostrar_apresentacao', 'mostrar_aprovacao_comite_etica', 'mostrar_apoio_financeiro']);
        });
    }
};
