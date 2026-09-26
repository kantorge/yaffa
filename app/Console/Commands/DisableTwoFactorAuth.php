<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:user:disable-2fa {email : The email address of the user to disable two-factor authentication for}')]
#[Description('Break-glass operator command: disable two-factor authentication for a user who lost both their authenticator device and recovery codes.')]
class DisableTwoFactorAuth extends Command
{
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

        Log::warning('Two-factor authentication disabled via break-glass operator command', [
            'user_id' => $user->id,
            'email' => $user->email,
            'command' => $this->signature,
        ]);

        $this->info(__('Two-factor authentication has been disabled for :email.', ['email' => $user->email]));

        return self::SUCCESS;
    }
}
