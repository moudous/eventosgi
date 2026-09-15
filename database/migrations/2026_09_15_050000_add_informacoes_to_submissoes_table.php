<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->longText('informacoes')->nullable()->after('titulo');
        });
    }

    public function down(): void
    {
        Schema::table('submissoes', fn (Blueprint $table) => $table->dropColumn('informacoes'));
    }
};
