<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->string('tipo_link_transmissao', 20)->nullable()->after('modalidade');
            $table->text('link_transmissao')->nullable()->after('tipo_link_transmissao');
        });
    }

    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->dropColumn(['tipo_link_transmissao', 'link_transmissao']);
        });
    }
};
