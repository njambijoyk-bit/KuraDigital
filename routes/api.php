<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    // Campaigns
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy']);

    // Campaign Members
    Route::get('/campaigns/{campaign}/members', [CampaignController::class, 'members']);
    Route::post('/campaigns/{campaign}/members', [CampaignController::class, 'inviteMember']);
    Route::put('/campaigns/{campaign}/members/{member}', [CampaignController::class, 'updateMember']);
    Route::delete('/campaigns/{campaign}/members/{member}', [CampaignController::class, 'removeMember']);
});

// Public site routes (no auth required)
Route::get('/sites/{slug}', [SiteController::class, 'show']);
Route::get('/sites/{siteId}/manifesto', [SiteController::class, 'manifesto']);
Route::get('/sites/{siteId}/events', [SiteController::class, 'events']);
Route::get('/sites/{siteId}/news', [SiteController::class, 'news']);
Route::get('/sites/{siteId}/gallery', [SiteController::class, 'gallery']);
Route::get('/sites/{siteId}/projects', [SiteController::class, 'projects']);
Route::post('/sites/{siteId}/contact', [SiteController::class, 'storeContact']);
Route::post('/sites/{siteId}/volunteers', [SiteController::class, 'storeVolunteer']);
