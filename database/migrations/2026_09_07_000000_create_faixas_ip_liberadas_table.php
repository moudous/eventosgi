<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Redes conhecidas da instituicao -- o wi-fi de um campus, o laboratorio de uma
        // faculdade -- onde muita gente se inscreve pelo mesmo IP. Nelas os limites por
        // rede do envio de codigo nao se aplicam: veja LimiteEnvioCodigoService.
        Schema::create('faixas_ip_liberadas', function (Blueprint $table): void {
            $table->id();
            // Guardada ja normalizada em notacao CIDR (198.51.100.0/24, 2001:db8::/32).
            $table->string('faixa', 60)->unique();
            $table->string('descricao', 150);
            $table->boolean('ativo')->default(true)->index();
            $table->unsignedBigInteger('criado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faixas_ip_liberadas');
    }
};
