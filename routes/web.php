<?php

use Illuminate\Support\Facades\Route;

// Catch-all: every non-API request loads the SPA shell
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
