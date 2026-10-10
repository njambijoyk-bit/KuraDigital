<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;

/**
 * What the current workspace is licensed for. Core (no module key): any
 * member of the workspace can read it, so the UI can hide or lock features.
 */
class LicenseController extends Controller
{
    public function show(Campaign $campaign, LicenseService $license): JsonResponse
    {
        $organisation = $campaign->organisation;

        if (!$organisation) {
            return response()->json([
                'enforced' => $license->enforced(),
                'organisation' => null,
                'modules' => [],
            ]);
        }

        return response()->json(['enforced' => $license->enforced()] + $license->summary($organisation));
    }
}
