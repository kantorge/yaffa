<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;

class DebugTransaction extends Command
{
    protected $signature = 'debug:transaction {id : Transaction ID} {--raw : Output raw var_export instead of JSON}';

    protected $description = 'Dump transaction with relations for debugging';

    public function handle()
    {
        $id = (int) $this->argument('id');

        // Load basic relations first. We will conditionally load config sub-relations
        $tx = Transaction::with([
            'transactionType',
            'transactionItems',
            'transactionItems.category',
            'transactionItems.tags',
            'transactionSchedule',
            'config',
        ])->find($id);

        if (! $tx) {
            $this->error("Transaction {$id} not found");
            return 1;
        }

        // Prepare an array representation, avoiding circular relations
        $data = [
            'id' => $tx->id,
            'user_id' => $tx->user_id,
            'date' => $tx->date?->toDateString(),
            'transaction_type' => $tx->transactionType?->toArray(),
            'transaction_items' => $tx->transactionItems->map(function($it){
                return [
                    'id' => $it->id,
                    'amount' => $it->amount,
                    'comment' => $it->comment,
                    'category' => $it->category?->only(['id','name']),
                    'tags' => $it->tags->map(fn($t)=>$t->only(['id','name'])),
                ];
            })->toArray(),
            'config_type' => $tx->config_type,
            'config' => null,
            'cashflow_value' => $tx->cashflow_value,
            'comment' => $tx->comment,
            'schedule' => $tx->schedule,
            'budget' => $tx->budget,
        ];

        if ($tx->config) {
            $cfg = $tx->config;
            // Conditionally eager-load config relations depending on config type
            try {
                if ($tx->config_type === 'standard') {
                    $tx->loadMissing(['config.accountFrom', 'config.accountTo']);
                } elseif ($tx->config_type === 'investment') {
                    $tx->loadMissing(['config.account', 'config.investment']);
                }
            } catch (\Throwable $e) {
                // ignore missing relation errors here; we'll surface what we can
            }

            $cfgArr = $cfg->toArray();
            if (method_exists($cfg, 'accountFrom')) {
                $cfgArr['account_from'] = $cfg->accountFrom?->only(['id','name','config_type']);
            }
            if (method_exists($cfg, 'accountTo')) {
                $cfgArr['account_to'] = $cfg->accountTo?->only(['id','name','config_type']);
            }
            if (method_exists($cfg, 'account')) {
                $cfgArr['account'] = $cfg->account?->only(['id','name']);
            }
            if (method_exists($cfg, 'investment')) {
                $cfgArr['investment'] = $cfg->investment?->only(['id','name','symbol']);
            }
            $data['config'] = $cfgArr;
        }

        if ($this->option('raw')) {
            $this->line(var_export($data, true));
        } else {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
        }

        return 0;
    }
}
