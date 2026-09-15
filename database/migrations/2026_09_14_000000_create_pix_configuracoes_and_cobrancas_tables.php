<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pix_configuracoes', function (Blueprint $table): void {
            $table->id();
            $table->boolean('ativo')->default(false);
            $table->string('ambiente', 20)->default('producao');
            $table->text('client_id');
            $table->text('chave_pix');
            $table->text('certificado_pem');
            $table->text('chave_privada_pem');
            $table->text('senha_chave')->nullable();
            $table->string('token_url', 500);
            $table->string('api_url', 500);
            $table->timestamps();
        });

        Schema::create('pix_cobrancas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inscricao_atividade_id')->index();
            $table->string('campo', 150);
            $table->string('txid', 35)->unique();
            $table->decimal('valor', 12, 2);
            $table->string('status', 30)->default('CRIADA')->index();
            $table->text('pix_copia_cola')->nullable();
            $table->string('location', 1000)->nullable();
            $table->json('resposta_api')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();
            $table->unique(['inscricao_atividade_id', 'campo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_cobrancas');
        Schema::dropIfExists('pix_configuracoes');
    }
};
