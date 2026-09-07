<?php

use App\Http\Controllers\Api\FormularioPublicoController;
use Illuminate\Support\Facades\Route;

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
