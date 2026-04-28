<?php

use Illuminate\Support\Facades\Route;

Route::get('/dashboard/{any?}', function () {
    return view('dashboard');
})->where('any', '.*');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
