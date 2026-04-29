<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('viewAny', [AuditLog::class, $campaign]);

        $query = AuditLog::where('campaign_id', $campaign->id)
            ->with('user:id,name,email');

        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $logs = $query->orderByDesc('created_at')->paginate(50);

        return response()->json($logs);
    }

    public function show(Campaign $campaign, AuditLog $auditLog): JsonResponse
    {
        $this->authorize('viewAny', [AuditLog::class, $campaign]);

        if ($auditLog->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Audit log not found.'], 404);
        }

        return response()->json([
            'audit_log' => $auditLog->load('user:id,name,email'),
        ]);
    }
}
