<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\FormulaController;
use App\Http\Controllers\Api\SimulationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Formulas
    Route::get('/formulas', [FormulaController::class, 'index']);
    Route::post('/formulas', [FormulaController::class, 'store']);
    Route::get('/formulas/{id}', [FormulaController::class, 'show']);
    Route::post('/formulas/{id}/activate', [FormulaController::class, 'activate']);
    Route::post('/formulas/{id}/validate', [FormulaController::class, 'validate']);

    // Commission
    Route::post('/commission/calculate', [CommissionController::class, 'calculate']);
    Route::get('/commission/history', [CommissionController::class, 'history']);
    Route::get('/commission/history/{id}', [CommissionController::class, 'historyShow']);

    // Simulation
    Route::post('/simulation/run', [SimulationController::class, 'run']);
    Route::get('/simulation/{id}', [SimulationController::class, 'show']);

    // Audit
    Route::get('/audit', [AuditController::class, 'index']);
    Route::get('/audit/{id}', [AuditController::class, 'show']);

    // Contracts
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::put('/contracts/{id}', [ContractController::class, 'update']);
    Route::delete('/contracts/{id}', [ContractController::class, 'destroy']);
});
