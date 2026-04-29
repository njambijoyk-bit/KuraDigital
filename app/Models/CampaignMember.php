<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function hasGeographicAccessTo(Model $resource): bool
    {
        if (method_exists($resource, 'getAttribute')) {
            $ward = $resource->getAttribute('ward');
            $constituency = $resource->getAttribute('constituency');
            $county = $resource->getAttribute('county');

            if ($ward && !$this->hasWardAccess($ward)) {
                return false;
            }
            if ($constituency && !$this->hasConstituencyAccess($constituency)) {
                return false;
            }
            if ($county && !$this->hasCountyAccess($county)) {
                return false;
            }
        }

        return true;
    }

    public function applyGeographicFilters(Builder $query, array $fields = ['ward', 'constituency', 'county']): Builder
    {
        if (in_array('ward', $fields) && !empty($this->assigned_wards)) {
            $query->where(function (Builder $q) {
                $q->whereIn('ward', $this->assigned_wards)
                    ->orWhereNull('ward');
            });
        }

        if (in_array('constituency', $fields) && !empty($this->assigned_constituencies)) {
            $query->where(function (Builder $q) {
                $q->whereIn('constituency', $this->assigned_constituencies)
                    ->orWhereNull('constituency');
            });
        }

        if (in_array('county', $fields) && !empty($this->assigned_counties)) {
            $query->where(function (Builder $q) {
                $q->whereIn('county', $this->assigned_counties)
                    ->orWhereNull('county');
            });
        }

        return $query;
    }

    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
