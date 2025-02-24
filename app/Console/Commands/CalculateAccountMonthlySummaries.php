<?php

namespace App\Console\Commands;

use App\Jobs\CalculateAccountMonthlySummary as CalculateAccountMonthlySummariesJob;
use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

class CalculateAccountMonthlySummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cache:account-monthly-summaries '
        . '{accountEntityId? : The ID of the account entity to process directly. Takes precedence if set together with the User ID.} '
        . '{userId? : The ID of the user to process all their accounts.}'
        . '{transactionType? : One of \'account_balance\', \'investment_value\'. All types get processed if not set.} '
        . '{dataType? : One of \'fact\', \'forecast\', \'budget\'. If not set, all types get processed.} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and cache the monthly summaries for accounts.';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle(): void
    {
        // Get the optional parameters
        $accountEntityId = $this->argument('accountEntityId');
        $userId = $this->argument('userId');

        // The accountEntity must be a valid account entity ID, or null
        if ($accountEntityId !== null) {
            $this->handleSpecifiedAccountEntity((int) $accountEntityId);
            return;
        }

        // Get all accounts of all users (active and inactive)
        if ($userId !== null) {
            $user = User::findOrFail($userId, 'Invalid userId');
            $users = collect([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function ($user) {
            // Load all accounts for the user
            $user->load('accounts');

            $jobs = [];
            // Loop through all accounts and create a job for each account
            $user->accounts->each(function ($account) use ($user, &$jobs) {
                $jobs['account_balance-fact'][] = new CalculateAccountMonthlySummariesJob(
                    $user,
                    'account_balance-fact',
                    $account
                );

                $jobs['account_balance-forecast'][] = new CalculateAccountMonthlySummariesJob(
                    $user,
                    'account_balance-forecast',
                    $account
                );

                $jobs['investment_value-fact'][] = new CalculateAccountMonthlySummariesJob(
                    $user,
                    'investment_value-fact',
                    $account
                );

                $jobs['investment_value-forecast'][] = new CalculateAccountMonthlySummariesJob(
                    $user,
                    'investment_value-forecast',
                    $account
                );

                $jobs['account_balance-budget'][] = new CalculateAccountMonthlySummariesJob(
                    $user,
                    'account_balance-budget',
                    $account
                );
            });

            // Finally, add the generic budget job, which handles empty accounts
            $jobs['account_balance-budget'][] = new CalculateAccountMonthlySummariesJob(
                $user,
                'account_balance-budget'
            );

            // Now we need to dispatch the jobs prepared above
            if (array_key_exists('account_balance-fact', $jobs)) {
                Bus::batch($jobs['account_balance-fact'])
                    ->name('CalculateAccountMonthlySummariesJob-account_balance-fact-' . $user->id)
                    ->dispatch();
            }

            if (array_key_exists('investment_value-fact', $jobs)) {
                Bus::batch($jobs['investment_value-fact'])
                    ->name('CalculateAccountMonthlySummariesJob-investment_value-fact-' . $user->id)
                    ->dispatch();
            }

            if (array_key_exists('account_balance-forecast', $jobs)) {
                Bus::batch($jobs['account_balance-forecast'])
                    ->name('CalculateAccountMonthlySummariesJob-account_balance-forecast-' . $user->id)
                    ->dispatch();
            }

            if (array_key_exists('investment_value-forecast', $jobs)) {
                Bus::batch($jobs['investment_value-forecast'])
                    ->name('CalculateAccountMonthlySummariesJob-investment_value-forecast-' . $user->id)
                    ->dispatch();
            }

            if (array_key_exists('account_balance-budget', $jobs)) {
                Bus::batch($jobs['account_balance-budget'])
                    ->name('CalculateAccountMonthlySummariesJob-account_balance-budget-' . $user->id)
                    ->dispatch();
            }
        });
    }

    /**
     * @param int $accountEntityId
     * @throws Throwable
     */
    public function handleSpecifiedAccountEntity(int $accountEntityId): void
    {
        $accountEntity = AccountEntity::find($accountEntityId);

        if ($accountEntity === null) {
            $this->error('Invalid accountEntityId');
            return;
        }

        // Check if the given account entity is an account
        if (!$accountEntity->isAccount()) {
            $this->error('The given accountEntityId is not an account');
            return;
        }

        $user = $accountEntity->user;

        // Transaction type and data type are only needed if accountEntity is set
        $transactionType = $this->argument('transactionType');

        // The transactionType must be 'account_balance' or 'investment_value' or null
        if ($transactionType !== null && !in_array($transactionType, ['account_balance', 'investment_value'])) {
            $this->error('Invalid transactionType');
            return;
        }

        $dataType = $this->argument('dataType');

        // The dataType must be 'fact', 'forecast', 'budget' or null
        if ($dataType !== null && !in_array($dataType, ['fact', 'forecast', 'budget'])) {
            $this->error('Invalid dataType');
            return;
        }

        // Create a job for the given accountEntity, transactionType and dataType
        if ($transactionType === 'account_balance' || $transactionType === null) {
            if ($dataType === 'fact' || $dataType === null) {
                Bus::batch([
                    new CalculateAccountMonthlySummariesJob($user, 'account_balance-fact', $accountEntity)
                ])
                    ->name('CalculateAccountMonthlySummariesJob-account_balance-fact-' . $user->id)
                    ->dispatch();
            }

            if ($dataType === 'forecast' || $dataType === null) {
                Bus::batch([
                    new CalculateAccountMonthlySummariesJob($user, 'account_balance-forecast', $accountEntity)
                ])
                    ->name('CalculateAccountMonthlySummariesJob-account_balance-forecast-' . $user->id)
                    ->dispatch();
            }

            if ($dataType === 'budget' || $dataType === null) {
                Bus::batch([
                    new CalculateAccountMonthlySummariesJob($user, 'account_balance-budget', $accountEntity)
                ])
                    ->name('CalculateAccountMonthlySummariesJob-account_balance-budget-' . $user->id)
                    ->dispatch();
            }
        }

        if ($transactionType === 'investment_value' || $transactionType === null) {
            if ($dataType === 'fact' || $dataType === null) {
                Bus::batch([
                    new CalculateAccountMonthlySummariesJob($user, 'investment_value-fact', $accountEntity)
                ])
                    ->name('CalculateAccountMonthlySummariesJob-investment_value-fact-' . $user->id)
                    ->dispatch();
            }

            if ($dataType === 'forecast' || $dataType === null) {
                Bus::batch([
                    new CalculateAccountMonthlySummariesJob($user, 'investment_value-forecast', $accountEntity)
                ])
                    ->name('CalculateAccountMonthlySummariesJob-investment_value-forecast-' . $user->id)
                    ->dispatch();
            }
        }
    }
}
