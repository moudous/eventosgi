<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pix_configuracoes', function (Blueprint $table): void {
            $table->text('sandbox_token')->nullable()->after('client_secret');
            $table->text('certificado_pem')->nullable()->change();
            $table->text('chave_privada_pem')->nullable()->change();
            $table->string('token_url', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pix_configuracoes', function (Blueprint $table): void {
            $table->dropColumn('sandbox_token');
            $table->text('certificado_pem')->nullable(false)->change();
            $table->text('chave_privada_pem')->nullable(false)->change();
            $table->string('token_url', 500)->nullable(false)->change();
        });
    }
};
