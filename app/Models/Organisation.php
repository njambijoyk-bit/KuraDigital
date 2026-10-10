<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'contact_name',
        'contact_email',
        'status',
        'max_workspaces',
        'notes',
    ];

    protected $casts = [
        'max_workspaces' => 'integer',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(OrganisationModule::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function getAuditCampaignId(): ?int
    {
        return null;
    }
}
