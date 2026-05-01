<?php

use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ContactMessageController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\ManifestoController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NewsController;
use App\Http\Controllers\Api\V1\OpponentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SiteSettingsController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\VoterController;
use App\Http\Controllers\Api\V1\VolunteerController;
use App\Http\Controllers\Api\V1\FieldAgentController;
use App\Http\Controllers\Api\V1\SurveyController;
use App\Http\Controllers\Api\V1\CheckInController;
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

    // Auth (public, rate-limited)
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/verify-mfa', [AuthController::class, 'verifyMfa'])->middleware('throttle:auth-mfa');

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
        Route::post('/auth/mfa/setup', [AuthController::class, 'setupMfa']);
        Route::post('/auth/mfa/confirm', [AuthController::class, 'confirmMfa']);
        Route::post('/auth/mfa/disable', [AuthController::class, 'disableMfa']);

        // Campaigns (list / create — no campaign context needed)
        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::post('/campaigns', [CampaignController::class, 'store'])->middleware('throttle:campaign-create');

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
            Route::post('/invite', [TeamController::class, 'invite'])->middleware('throttle:campaign-invite');
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

            // --- Phase 1B: Content Management ---

            // Site settings
            Route::get('/site', [SiteSettingsController::class, 'show']);
            Route::post('/site', [SiteSettingsController::class, 'store']);
            Route::put('/site', [SiteSettingsController::class, 'update']);
            Route::delete('/site', [SiteSettingsController::class, 'destroy']);

            // Manifesto
            Route::get('/manifesto', [ManifestoController::class, 'index']);
            Route::post('/manifesto', [ManifestoController::class, 'store']);
            Route::get('/manifesto/{pillar}', [ManifestoController::class, 'show']);
            Route::put('/manifesto/{pillar}', [ManifestoController::class, 'update']);
            Route::delete('/manifesto/{pillar}', [ManifestoController::class, 'destroy']);

            // Events
            Route::get('/events', [EventController::class, 'index']);
            Route::post('/events', [EventController::class, 'store']);
            Route::get('/events/{event}', [EventController::class, 'show']);
            Route::put('/events/{event}', [EventController::class, 'update']);
            Route::delete('/events/{event}', [EventController::class, 'destroy']);

            // News
            Route::get('/news', [NewsController::class, 'index']);
            Route::post('/news', [NewsController::class, 'store']);
            Route::get('/news/{article}', [NewsController::class, 'show']);
            Route::put('/news/{article}', [NewsController::class, 'update']);
            Route::delete('/news/{article}', [NewsController::class, 'destroy']);

            // Gallery
            Route::get('/gallery', [GalleryController::class, 'index']);
            Route::post('/gallery', [GalleryController::class, 'store']);
            Route::get('/gallery/{item}', [GalleryController::class, 'show']);
            Route::put('/gallery/{item}', [GalleryController::class, 'update']);
            Route::delete('/gallery/{item}', [GalleryController::class, 'destroy']);

            // Projects
            Route::get('/projects', [ProjectController::class, 'index']);
            Route::post('/projects', [ProjectController::class, 'store']);
            Route::get('/projects/{project}', [ProjectController::class, 'show']);
            Route::put('/projects/{project}', [ProjectController::class, 'update']);
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

            // Volunteers
            Route::get('/volunteers', [VolunteerController::class, 'index']);
            Route::get('/volunteers/export', [VolunteerController::class, 'export']);
            Route::get('/volunteers/{volunteer}', [VolunteerController::class, 'show']);
            Route::put('/volunteers/{volunteer}', [VolunteerController::class, 'update']);
            Route::delete('/volunteers/{volunteer}', [VolunteerController::class, 'destroy']);

            // Contact messages
            Route::get('/contacts', [ContactMessageController::class, 'index']);
            Route::get('/contacts/export', [ContactMessageController::class, 'export']);
            Route::get('/contacts/{message}', [ContactMessageController::class, 'show']);
            Route::put('/contacts/{message}', [ContactMessageController::class, 'update']);
            Route::delete('/contacts/{message}', [ContactMessageController::class, 'destroy']);

            // Voters
            Route::get('/voters', [VoterController::class, 'index']);
            Route::post('/voters', [VoterController::class, 'store']);
            Route::get('/voters/stats', [VoterController::class, 'stats']);
            Route::get('/voters/export', [VoterController::class, 'export']);
            Route::post('/voters/import', [VoterController::class, 'import']);
            Route::post('/voters/bulk-tag', [VoterController::class, 'bulkTag']);
            Route::post('/voters/bulk-status', [VoterController::class, 'bulkUpdateStatus']);
            Route::get('/voters/{voter}', [VoterController::class, 'show']);
            Route::put('/voters/{voter}', [VoterController::class, 'update']);
            Route::delete('/voters/{voter}', [VoterController::class, 'destroy']);

            // Opponents
            Route::get('/opponents', [OpponentController::class, 'index']);
            Route::post('/opponents', [OpponentController::class, 'store']);
            Route::get('/opponents/{opponent}', [OpponentController::class, 'show']);
            Route::put('/opponents/{opponent}', [OpponentController::class, 'update']);
            Route::delete('/opponents/{opponent}', [OpponentController::class, 'destroy']);

            // Opponent research
            Route::get('/opponents/{opponent}/research', [OpponentController::class, 'researchIndex']);
            Route::post('/opponents/{opponent}/research', [OpponentController::class, 'researchStore']);
            Route::put('/opponents/{opponent}/research/{research}', [OpponentController::class, 'researchUpdate']);
            Route::delete('/opponents/{opponent}/research/{research}', [OpponentController::class, 'researchDestroy']);

            // --- Phase 1D: Field Operations ---

            // Field agents
            Route::get('/field-agents', [FieldAgentController::class, 'index']);
            Route::post('/field-agents', [FieldAgentController::class, 'store']);
            Route::get('/field-agents/locations', [FieldAgentController::class, 'locations']);
            Route::get('/field-agents/{fieldAgent}', [FieldAgentController::class, 'show']);
            Route::put('/field-agents/{fieldAgent}', [FieldAgentController::class, 'update']);
            Route::delete('/field-agents/{fieldAgent}', [FieldAgentController::class, 'destroy']);
            Route::post('/field-agents/{fieldAgent}/assign-station', [FieldAgentController::class, 'assignStation']);

            // Surveys
            Route::get('/surveys', [SurveyController::class, 'index']);
            Route::post('/surveys', [SurveyController::class, 'store']);
            Route::get('/surveys/{survey}', [SurveyController::class, 'show']);
            Route::put('/surveys/{survey}', [SurveyController::class, 'update']);
            Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy']);
            Route::post('/surveys/{survey}/submit', [SurveyController::class, 'submit']);
            Route::get('/surveys/{survey}/responses', [SurveyController::class, 'responses']);

            // Check-ins
            Route::get('/check-ins', [CheckInController::class, 'index']);
            Route::post('/check-ins', [CheckInController::class, 'store']);
        });
    });
});
