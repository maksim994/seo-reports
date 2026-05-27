<?php

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;

class IntegrationPolicy
{
    public function view(User $user, Integration $integration): bool
    {
        return $user->id === $integration->user_id;
    }

    public function delete(User $user, Integration $integration): bool
    {
        return $user->id === $integration->user_id;
    }
}
