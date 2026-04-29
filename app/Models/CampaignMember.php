<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMember extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'role',
        'visibility_scope',
        'assigned_wards',
        'assigned_constituencies',
        'assigned_counties',
        'assigned_polling_stations',
        'is_active',
        'joined_at',
        'deactivated_at',
    ];

    protected $casts = [
        'assigned_wards' => 'array',
        'assigned_constituencies' => 'array',
        'assigned_counties' => 'array',
        'assigned_polling_stations' => 'array',
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function hasWardAccess(string $ward): bool
    {
        $wards = $this->assigned_wards;
        if (empty($wards)) {
            return true;
        }
        return in_array($ward, $wards, true);
    }

    public function hasConstituencyAccess(string $constituency): bool
    {
        $constituencies = $this->assigned_constituencies;
        if (empty($constituencies)) {
            return true;
        }
        return in_array($constituency, $constituencies, true);
    }

    public function hasCountyAccess(string $county): bool
    {
        $counties = $this->assigned_counties;
        if (empty($counties)) {
            return true;
        }
        return in_array($county, $counties, true);
    }

    public function hasPollingStationAccess(string $station): bool
    {
        $stations = $this->assigned_polling_stations;
        if (empty($stations)) {
            return true;
        }
        return in_array($station, $stations, true);
    }

    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
