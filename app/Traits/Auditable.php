<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $oldValues = array_intersect_key(
                $model->getOriginal(),
                $model->getDirty()
            );
            $newValues = $model->getDirty();

            // Don't log if nothing meaningful changed
            if (empty($newValues)) {
                return;
            }

            // Exclude password hashes and tokens from audit
            $exclude = ['password', 'remember_token', 'mfa_secret', 'invite_token', 'token'];
            $oldValues = array_diff_key($oldValues, array_flip($exclude));
            $newValues = array_diff_key($newValues, array_flip($exclude));

            static::logAudit($model, 'updated', $oldValues, $newValues);
        });

        static::deleted(function ($model) {
            static::logAudit($model, 'deleted', $model->getAttributes(), []);
        });
    }

    protected static function logAudit($model, string $action, array $oldValues, array $newValues): void
    {
        $campaignId = method_exists($model, 'getAuditCampaignId')
            ? $model->getAuditCampaignId()
            : ($model->campaign_id ?? null);

        AuditLog::create([
            'user_id' => Auth::id(),
            'campaign_id' => $campaignId,
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'device_type' => static::detectDeviceType(Request::userAgent()),
            'created_at' => now(),
        ]);
    }

    protected static function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        if (preg_match('/mobile|android|iphone/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }
}
