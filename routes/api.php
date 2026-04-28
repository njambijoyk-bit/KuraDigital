<?php

use App\Http\Controllers\Api\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/sites/{slug}', [SiteController::class, 'show']);
Route::get('/sites/{siteId}/manifesto', [SiteController::class, 'manifesto']);
Route::get('/sites/{siteId}/events', [SiteController::class, 'events']);
Route::get('/sites/{siteId}/news', [SiteController::class, 'news']);
Route::get('/sites/{siteId}/gallery', [SiteController::class, 'gallery']);
Route::get('/sites/{siteId}/projects', [SiteController::class, 'projects']);
Route::post('/sites/{siteId}/contact', [SiteController::class, 'storeContact']);
Route::post('/sites/{siteId}/volunteers', [SiteController::class, 'storeVolunteer']);
