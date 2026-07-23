<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DisableTwoFactorAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user:disable-2fa {email : The email address of the user to disable two-factor authentication for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Break-glass operator command: disable two-factor authentication for a user who lost both their authenticator device and recovery codes.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error(__('No user found with that email address.'));

            return self::FAILURE;
        }

        if (! $user->hasTwoFactorEnabled()) {
            $this->info(__('Two-factor authentication is not enabled for this user.'));

            return self::SUCCESS;
        }

        $user->disableTwoFactorAuth();

        $this->info(__('Two-factor authentication has been disabled for :email.', ['email' => $user->email]));

        return self::SUCCESS;
    }
}
