<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pix_configuracoes', fn (Blueprint $table) => $table->text('client_secret')->nullable()->after('client_id'));
    }

    public function down(): void
    {
        Schema::table('pix_configuracoes', fn (Blueprint $table) => $table->dropColumn('client_secret'));
    }
};
