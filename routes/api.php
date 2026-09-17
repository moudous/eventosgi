<?php

use App\Http\Controllers\Api\FormularioPublicoController;
use App\Http\Controllers\Api\SicoobPixWebhookController;
use Illuminate\Support\Facades\Route;

// Endpoint público do PSP: sem sessão, CSRF, permissão do GI ou token do formulário.
// O Sicoob recebe /api/sicoob como URL-base e publica as notificações em /pix.
Route::get('/sicoob/pix', [SicoobPixWebhookController::class, 'status'])
    ->middleware('throttle:60,1')->name('sicoob.pix.status');
Route::post('/sicoob/pix', [SicoobPixWebhookController::class, 'receber'])
    ->middleware('throttle:120,1')->name('sicoob.pix.webhook');

Route::middleware('formulario.token')->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/formularios/{atividade}', [FormularioPublicoController::class, 'mostrar'])->name('formularios.mostrar');

    // Identificacao por e-mail: o consumidor externo pede o codigo, confere e guarda o token
    // devolvido aqui, reenviando-o em X-Identificacao-Token nas chamadas seguintes.
    Route::post('/formularios/{atividade}/identificacao/codigo', [FormularioPublicoController::class, 'solicitarCodigo'])->name('formularios.identificacao.codigo');
    Route::post('/formularios/{atividade}/identificacao', [FormularioPublicoController::class, 'identificar'])->name('formularios.identificacao.validar');
    Route::get('/formularios/{atividade}/identificacao', [FormularioPublicoController::class, 'identificacao'])->name('formularios.identificacao.mostrar');
    Route::delete('/formularios/{atividade}/identificacao', [FormularioPublicoController::class, 'encerrarIdentificacao'])->name('formularios.identificacao.encerrar');

    Route::post('/formularios/{atividade}/inscricoes', [FormularioPublicoController::class, 'inscrever'])->name('formularios.inscrever');
});
