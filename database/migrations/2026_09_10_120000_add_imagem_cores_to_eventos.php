<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('imagem')->nullable();
            $table->json('cores')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eventos', fn (Blueprint $table) => $table->dropColumn(['imagem', 'cores']));
    }
};
