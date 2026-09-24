<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codigos_inscricao', function (Blueprint $table): void {
            $table->unsignedBigInteger('atividade_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('codigos_inscricao', function (Blueprint $table): void {
            $table->dropIndex(['atividade_id']);
            $table->dropColumn('atividade_id');
        });
    }
};
