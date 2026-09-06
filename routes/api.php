<?php

use App\Http\Controllers\Api\FormularioPublicoController;
use Illuminate\Support\Facades\Route;

Route::middleware('formulario.token')->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/formularios/{atividade}', [FormularioPublicoController::class, 'mostrar'])->name('formularios.mostrar');
    Route::post('/formularios/{atividade}/inscricoes', [FormularioPublicoController::class, 'inscrever'])->name('formularios.inscrever');
});
