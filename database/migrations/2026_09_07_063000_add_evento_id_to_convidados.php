<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convidados', function (Blueprint $table): void {
            $table->foreignId('evento_id')->nullable()->after('id')->constrained('eventos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('convidados', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('evento_id');
        });
    }
};
