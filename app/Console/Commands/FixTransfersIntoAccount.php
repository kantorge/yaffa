<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FixTransfersIntoAccount extends Command
{
    protected $signature = 'fix:transfers-into {target : AccountEntity ID to set as account_to} {--since= : Limit to transactions since date (Y-m-d)} {--dry-run : Do not perform writes} {--confirm : Confirm making changes}';

    protected $description = 'Fix transfer transactions where one side is not an account by setting account_from to the account side and account_to to the target account';

    public function handle()
    {
        $target = (int) $this->argument('target');
        $since = $this->option('since');
        $dry = $this->option('dry-run');
        $confirm = $this->option('confirm');

        // Validate target exists and is account
        $targetRow = DB::table('account_entities')->where('id', $target)->first();
        if (! $targetRow) {
            $this->error("Target account entity {$target} not found");
            return 1;
        }
        if ($targetRow->config_type !== 'account') {
            $this->error("Target entity {$target} is not an account (config_type={$targetRow->config_type})");
            return 1;
        }

        $query = DB::table('transactions as t')
            ->join('transaction_types as tt', 'tt.id', '=', 't.transaction_type_id')
            ->join('transaction_details_standard as d', 'd.id', '=', 't.config_id')
            ->leftJoin('account_entities as af', 'af.id', '=', 'd.account_from_id')
            ->leftJoin('account_entities as at', 'at.id', '=', 'd.account_to_id')
            ->select(
                't.id as transaction_id', 't.config_id', 't.date', 't.comment',
                'd.account_from_id', 'af.name as account_from_name', 'af.config_type as account_from_type',
                'd.account_to_id', 'at.name as account_to_name', 'at.config_type as account_to_type',
                'd.amount_from', 'd.amount_to'
            )
            ->where('tt.name', 'transfer')
            ->where(function ($q) {
                $q->whereNull('af.config_type')
                  ->orWhere('af.config_type', '!=', 'account')
                  ->orWhereNull('at.config_type')
                  ->orWhere('at.config_type', '!=', 'account');
            })
            ->orderBy('t.date', 'desc');

        if ($since) {
            $query->where('t.date', '>=', $since);
        }

        $rows = $query->get();
        $count = $rows->count();
        $this->info("Found {$count} candidate transfer(s)");

        if ($count === 0) return 0;

        $plans = [];
        foreach ($rows as $r) {
            // Determine if we can repair: need one side is account and the other is not account
            $fromIsAccount = ($r->account_from_type === 'account');
            $toIsAccount = ($r->account_to_type === 'account');

            if ($fromIsAccount && ! $toIsAccount) {
                // already from is account, will set to target
                $newFrom = $r->account_from_id;
                $newTo = $target;
            } elseif (! $fromIsAccount && $toIsAccount) {
                // swap: set from to the current to, and to target
                $newFrom = $r->account_to_id;
                $newTo = $target;
            } else {
                // ambiguous - skip
                $plans[] = ['transaction_id' => $r->transaction_id, 'action' => 'skip_ambiguous', 'reason' => 'both_sides_non_account_or_both_accounts'];
                continue;
            }

            $plans[] = [
                'transaction_id' => $r->transaction_id,
                'config_id' => $r->config_id,
                'date' => $r->date,
                'old_from' => $r->account_from_id,
                'old_from_type' => $r->account_from_type,
                'old_to' => $r->account_to_id,
                'old_to_type' => $r->account_to_type,
                'new_from' => $newFrom,
                'new_to' => $newTo,
            ];
        }

        $this->info('Planned changes: ' . count(array_filter($plans, fn($p)=>($p['action'] ?? null) !== 'skip_ambiguous')));
        $this->line(json_encode(array_slice($plans,0,20), JSON_PRETTY_PRINT));

        if ($dry) {
            $this->info('Dry-run mode, no changes written');
            return 0;
        }

        if (! $confirm) {
            $this->error('No --confirm flag provided; aborting. Add --confirm to perform changes.');
            return 1;
        }

        $audit = [
            'target' => $target,
            'timestamp' => now()->toDateTimeString(),
            'changes' => [],
        ];

        foreach ($plans as $p) {
            if (($p['action'] ?? null) === 'skip_ambiguous') continue;

            DB::transaction(function() use ($p, &$audit) {
                // update transaction_details_standard
                DB::table('transaction_details_standard')
                    ->where('id', $p['config_id'])
                    ->update([
                        'account_from_id' => $p['new_from'],
                        'account_to_id' => $p['new_to'],
                    ]);

                // ensure transaction row exists and set type to transfer
                DB::table('transactions')
                    ->where('config_id', $p['config_id'])
                    ->update(['transaction_type_id' => DB::table('transaction_types')->where('name','transfer')->value('id')]);

                $audit['changes'][] = $p;
            });
        }

        // write audit
        $path = 'reports/transfer_fixes_' . now()->format('Ymd_His') . '.json';
        Storage::disk('local')->put($path, json_encode($audit, JSON_PRETTY_PRINT));
        $this->info("Applied changes and wrote audit to storage/app/{$path}");

        return 0;
    }
}
