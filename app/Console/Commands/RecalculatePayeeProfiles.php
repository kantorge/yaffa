<?php

namespace App\Console\Commands;

use App\Models\AccountEntity;
use App\Models\User;
use App\Services\PayeeProfileService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:payees:recalculate-profiles {userId? : Optional user ID for a scoped recalculation}')]
#[Description('Recalculate the history profile of every payee, including changes that fire no transaction event')]
class RecalculatePayeeProfiles extends Command
{
    public function handle(PayeeProfileService $service): int
    {
        $userId = $this->argument('userId');
        if ($userId !== null && User::query()->find((int) $userId) === null) {
            $this->error('Invalid userId');

            return Command::FAILURE;
        }

        $count = 0;

        AccountEntity::query()
            ->payees()
            ->when($userId !== null, fn ($query) => $query->where('user_id', (int) $userId))
            ->each(function (AccountEntity $payee) use ($service, &$count): void {
                $service->calculate($payee);
                $count++;
            });

        $this->info("Recalculated {$count} payee profile(s).");

        return Command::SUCCESS;
    }
}
