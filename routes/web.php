<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Catch-all: every other request loads the SPA shell
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!login|logout).*$');
