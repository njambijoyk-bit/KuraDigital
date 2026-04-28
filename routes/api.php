<?php

use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Site API (existing)
|--------------------------------------------------------------------------
*/
Route::get('/sites/{slug}', [SiteController::class, 'show']);
Route::get('/sites/{siteId}/manifesto', [SiteController::class, 'manifesto']);
Route::get('/sites/{siteId}/events', [SiteController::class, 'events']);
Route::get('/sites/{siteId}/news', [SiteController::class, 'news']);
Route::get('/sites/{siteId}/gallery', [SiteController::class, 'gallery']);
Route::get('/sites/{siteId}/projects', [SiteController::class, 'projects']);
Route::post('/sites/{siteId}/contact', [SiteController::class, 'storeContact']);
Route::post('/sites/{siteId}/volunteers', [SiteController::class, 'storeVolunteer']);

/*
|--------------------------------------------------------------------------
| V1 Campaign Manager API
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // Auth (public)
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/verify-mfa', [AuthController::class, 'verifyMfa']);

    // Accept invitation (authenticated but no campaign context)
    Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
        Route::post('/invitations/accept', [TeamController::class, 'acceptInvitation']);
    });

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'account.active'])->group(function () {

        // Auth management
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);
        Route::post('/auth/mfa/toggle', [AuthController::class, 'toggleMfa']);

        // Campaigns (list / create — no campaign context needed)
        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::post('/campaigns', [CampaignController::class, 'store']);

        // Campaign-scoped routes
        Route::middleware('campaign.access')->prefix('campaigns/{campaign}')->group(function () {

            // Campaign CRUD
            Route::get('/', [CampaignController::class, 'show']);
            Route::put('/', [CampaignController::class, 'update']);
            Route::delete('/', [CampaignController::class, 'destroy']);
            Route::get('/hierarchy', [CampaignController::class, 'hierarchy']);
            Route::get('/children', [CampaignController::class, 'children']);

            // Team management
            Route::get('/members', [TeamController::class, 'members']);
            Route::post('/invite', [TeamController::class, 'invite']);
            Route::put('/members/{member}', [TeamController::class, 'updateMember']);
            Route::delete('/members/{member}', [TeamController::class, 'deactivateMember']);
            Route::get('/invitations', [TeamController::class, 'pendingInvitations']);
            Route::delete('/invitations/{invitation}', [TeamController::class, 'revokeInvitation']);

            // Media library
            Route::get('/media', [MediaController::class, 'index']);
            Route::post('/media', [MediaController::class, 'store']);
            Route::get('/media/{media}', [MediaController::class, 'show']);
            Route::put('/media/{media}', [MediaController::class, 'update']);
            Route::delete('/media/{media}', [MediaController::class, 'destroy']);

            // Audit logs
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
            Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);
        });
    });
});
