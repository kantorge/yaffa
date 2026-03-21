<?php

namespace App\Console\Commands;

use App\Jobs\MergeStandardTransactionItemsJob;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class MergeStandardTransactionItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:transactions:merge-standard-items'
        . ' {userId? : The ID of the user whose transactions to process. All users are processed if not set.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge duplicate transaction items on existing standard transactions for legacy data cleanup, if enabled for the user.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('userId');

        if ($userId !== null) {
            $user = User::find((int) $userId);

            if ($user === null) {
                $this->error('No user found with ID ' . $userId . '.');
                return Command::FAILURE;
            }

            $users = collect([$user]);
        } else {
            $users = User::all();
        }

        $users->each(function (User $user): void {
            // Skip the user if they don't have the auto-merge setting enabled, to avoid unnecessary processing
            if (! $user->auto_merge_standard_transaction_items) {
                $this->info('Skipping user ' . $user->id . ' because auto-merge is not enabled.');
                return;
            }

            $jobs = Transaction::query()
                ->where('user_id', $user->id)
                ->where('config_type', 'standard')
                ->where('schedule', false)
                ->where('budget', false)
                ->select('id')
                ->cursor()
                ->map(fn ($row) => new MergeStandardTransactionItemsJob(
                    Transaction::findOrFail($row->id)
                ))
                ->all();

            if (empty($jobs)) {
                return;
            }

            Bus::batch($jobs)
                ->name('MergeStandardTransactionItemsJob-' . $user->id)
                ->dispatch();

            $this->info('Dispatched merge jobs for ' . count($jobs) . ' transactions of user ' . $user->id . '.');
        });

        return Command::SUCCESS;
    }
}
