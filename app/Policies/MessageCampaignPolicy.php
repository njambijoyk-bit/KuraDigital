<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\MessageCampaign;
use App\Models\User;

class MessageCampaignPolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.view', $campaign);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.create', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.edit', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.edit', $campaign);
    }

    public function approve(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('messaging.approve', $campaign);
    }

    public function send(User $user, Campaign $campaign, string $channel): bool
    {
        return match ($channel) {
            'sms' => $user->campaignCan('comms.send-sms', $campaign),
            'whatsapp' => $user->campaignCan('comms.send-whatsapp', $campaign),
            'email' => $user->campaignCan('comms.send-email', $campaign),
            default => false,
        };
    }
}
