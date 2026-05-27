<?php

namespace App\Policies;

use App\Models\ReportJob;
use App\Models\User;

class ReportJobPolicy
{
    public function view(User $user, ReportJob $reportJob): bool
    {
        return $user->id === $reportJob->user_id;
    }

    public function delete(User $user, ReportJob $reportJob): bool
    {
        return $user->id === $reportJob->user_id;
    }
}
