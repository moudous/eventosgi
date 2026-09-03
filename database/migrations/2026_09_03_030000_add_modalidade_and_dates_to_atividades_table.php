<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->string('modalidade', 20)->nullable()->after('evento_id');
            $table->dateTime('data_inicio')->nullable()->after('modalidade');
            $table->dateTime('data_fim')->nullable()->after('data_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->dropColumn(['modalidade', 'data_inicio', 'data_fim']);
        });
    }
};
