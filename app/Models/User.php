<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'clearance_level',
        'account_status',
        'mfa_enabled',
        'mfa_secret',
        'mfa_verified_at',
        'last_login_at',
        'last_login_ip',
        'invite_token',
        'invite_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'invite_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'mfa_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'invite_expires_at' => 'datetime',
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
    ];

    public function campaignMemberships(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(CampaignMember::class)->where('is_active', true);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_members')
            ->withPivot([
                'role', 'visibility_scope', 'assigned_wards', 'assigned_constituencies',
                'assigned_counties', 'assigned_polling_stations', 'is_active',
            ])
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->account_status === 'suspended';
    }

    public function hasClearance(string $level): bool
    {
        $levels = ['public' => 0, 'internal' => 1, 'confidential' => 2, 'top_secret' => 3];
        return ($levels[$this->clearance_level] ?? 0) >= ($levels[$level] ?? 0);
    }

    public function isMemberOf(Campaign $campaign): bool
    {
        return $this->campaignMemberships()
            ->where('campaign_id', $campaign->id)
            ->where('is_active', true)
            ->exists();
    }

    public function membershipFor(Campaign $campaign): ?CampaignMember
    {
        return $this->campaignMemberships()
            ->where('campaign_id', $campaign->id)
            ->where('is_active', true)
            ->first();
    }

    public function campaignRole(Campaign $campaign): ?string
    {
        $membership = $this->membershipFor($campaign);
        return $membership?->role;
    }

    public function campaignHasRole(string|array $roles, Campaign $campaign): bool
    {
        $campaignRole = $this->campaignRole($campaign);
        if (!$campaignRole) {
            return false;
        }
        return in_array($campaignRole, (array) $roles, true);
    }

    public function campaignCan(string $permission, Campaign $campaign): bool
    {
        $membership = $this->membershipFor($campaign);
        if (!$membership || !$membership->role) {
            return false;
        }

        $role = Role::findByName($membership->role, 'web');
        return $role->hasPermissionTo($permission);
    }
}
