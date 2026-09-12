<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\OperationsModuleController;
use App\Http\Controllers\ReadingSectionController;
use App\Http\Controllers\ShiftTeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route(auth()->user()->role === 'master' ? 'master' : 'operation.home')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/operacao', [OperationsModuleController::class, 'home'])->name('operation.home');
    Route::get('/leituras', [OperationController::class, 'readings'])->name('readings');
    Route::get('/master', [OperationController::class, 'master'])->name('master');

    Route::get('/acoes/{action?}', [OperationsModuleController::class, 'actions'])->name('actions.show');
    Route::post('/acoes', [OperationsModuleController::class, 'createAction'])->name('actions.create');
    Route::post('/acoes/{action}/iniciar', [OperationsModuleController::class, 'startAction'])->name('actions.start');
    Route::post('/acoes/{action}/concluir', [OperationsModuleController::class, 'completeAction'])->name('actions.complete');
    Route::get('/evidencias/{evidence}', [OperationsModuleController::class, 'evidence'])->name('evidences.show');

    Route::get('/aspersao', [OperationsModuleController::class, 'aspersions'])->name('aspersion');
    Route::post('/aspersao', [OperationsModuleController::class, 'startAspersion'])->name('aspersion.start');
    Route::post('/aspersao/{aspersion}/finalizar', [OperationsModuleController::class, 'endAspersion'])->name('aspersion.end');

    Route::get('/ocorrencias', [OperationsModuleController::class, 'occurrences'])->name('occurrences');
    Route::post('/ocorrencias', [OperationsModuleController::class, 'createOccurrence'])->name('occurrences.create');
    Route::post('/ocorrencias/{occurrence}/acao', [OperationsModuleController::class, 'convertOccurrence'])->name('occurrences.convert');
    Route::get('/ocorrencias/{occurrence}/foto', [OperationsModuleController::class, 'occurrencePhoto'])->name('occurrences.photo');

    Route::get('/passagem-turno', [OperationsModuleController::class, 'handover'])->name('handover');
    Route::post('/passagem-turno', [OperationsModuleController::class, 'closeHandover'])->name('handover.close');
    Route::get('/mais', [OperationsModuleController::class, 'more'])->name('more');

    Route::get('/equipe', [ShiftTeamController::class, 'index'])->name('team.index');
    Route::put('/equipe', [ShiftTeamController::class, 'update'])->name('team.update');

    Route::prefix('api')->group(function () {
        Route::get('/operation-snapshot', [OperationController::class, 'snapshot']);
        Route::get('/reading-sections/{readingSection}', [ReadingSectionController::class, 'show']);
        Route::post('/reading-sections/{readingSection}/open', [ReadingSectionController::class, 'open']);
        Route::put('/reading-sections/{readingSection}', [ReadingSectionController::class, 'update']);
    });
});
