<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class NewsArticlePolicy
{
    public function viewAny(User $user, Campaign $campaign): bool
    {
        return $user->can('news.view') && $user->isMemberOf($campaign);
    }

    public function create(User $user, Campaign $campaign): bool
    {
        return $user->can('news.create') && $user->isMemberOf($campaign);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('news.edit') && $user->isMemberOf($campaign);
    }

    public function publish(User $user, Campaign $campaign): bool
    {
        return $user->can('news.publish') && $user->isMemberOf($campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('news.delete') && $user->isMemberOf($campaign);
    }
}
