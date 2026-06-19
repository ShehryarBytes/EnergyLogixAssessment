<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Registration is intentionally disabled — this is an internal admin tool
Route::get('/register', fn () => abort(404))->name('register');
Route::get('/register/{any}', fn () => abort(404))->where('any', '.*');

// All other routes load the SPA shell — auth middleware ensures only
// authenticated users reach it; unauthenticated requests redirect to /login.
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*')->middleware('auth');
