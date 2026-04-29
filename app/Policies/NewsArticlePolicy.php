<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class NewsArticlePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('news.view', $campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('news.create', $campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('news.edit', $campaign);
    }

    public function publish(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('news.publish', $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->campaignCan('news.delete', $campaign);
    }
}
