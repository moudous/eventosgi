<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', fn (Blueprint $table) => $table->json('personalizacao')->nullable());
    }

    public function down(): void
    {
        Schema::table('submissoes', fn (Blueprint $table) => $table->dropColumn('personalizacao'));
    }
};
