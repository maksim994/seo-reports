<?php

namespace App\Policies;

use App\Models\TechnicalAuditJob;
use App\Models\User;

class TechnicalAuditJobPolicy
{
    public function view(User $user, TechnicalAuditJob $technicalAuditJob): bool
    {
        return $user->id === $technicalAuditJob->user_id;
    }

    public function delete(User $user, TechnicalAuditJob $technicalAuditJob): bool
    {
        return $user->id === $technicalAuditJob->user_id;
    }
}
