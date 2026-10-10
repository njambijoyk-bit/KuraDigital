<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('module:finance'). Must run after campaign.access.
 */
class EnsureModuleLicensed
{
    public function __construct(private LicenseService $license)
    {
    }

    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (!$this->license->enforced() || $this->license->bypasses($request->user())) {
            return $next($request);
        }

        $campaign = $request->input('current_campaign') ?? $request->route('campaign');

        if (!$campaign instanceof Campaign) {
            $campaign = $campaign ? Campaign::find($campaign) : null;
        }

        if (!$campaign) {
            return response()->json(['message' => 'Campaign context required.'], 400);
        }

        $result = $this->license->check($campaign, $module);

        if ($result === LicenseService::OK) {
            return $next($request);
        }

        $name = config("licensing.modules.{$module}.name", $module);

        return response()->json([
            'message' => match ($result) {
                LicenseService::EXPIRED => "Your {$name} licence has expired. Renew it to keep using this feature.",
                LicenseService::REVOKED => "Your {$name} licence has been withdrawn. Contact support.",
                LicenseService::ORGANISATION_SUSPENDED => 'This organisation is suspended. Contact support.',
                LicenseService::NO_ORGANISATION => 'This workspace is not linked to a licensed organisation.',
                default => "{$name} is not included in your licence.",
            },
            'code' => $result,
            'module' => $module,
            'expires_at' => $this->license->grantFor($campaign, $module)?->expires_at,
        ], 403);
    }
}
