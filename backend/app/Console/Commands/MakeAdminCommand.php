<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin {email : User email}';

    protected $description = 'Grant admin role to a user by email';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $user->update(['is_admin' => true, 'is_blocked' => false]);

        $this->info("User {$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
