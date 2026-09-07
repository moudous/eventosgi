<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->string('ip', 45)->nullable()->after('resposta')->index();
            $table->string('user_agent', 512)->nullable()->after('ip');
            $table->json('dispositivo')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropIndex(['ip']);
            $table->dropColumn(['ip', 'user_agent', 'dispositivo']);
        });
    }
};
