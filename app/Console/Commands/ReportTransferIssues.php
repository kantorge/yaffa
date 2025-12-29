<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportTransferIssues extends Command
{
    protected $signature = 'report:transfer-issues {--export : Save report to storage file} {--since= : Limit to transactions since date (Y-m-d)}';

    protected $description = 'Find transfer transactions where either side is missing or not an account entity';

    public function handle()
    {
        $since = $this->option('since');

        $query = DB::table('transactions as t')
            ->join('transaction_types as tt', 'tt.id', '=', 't.transaction_type_id')
            ->join('transaction_details_standard as d', 'd.id', '=', 't.config_id')
            ->leftJoin('account_entities as af', 'af.id', '=', 'd.account_from_id')
            ->leftJoin('account_entities as at', 'at.id', '=', 'd.account_to_id')
            ->select(
                't.id', 't.user_id', 't.date', 't.comment',
                'd.account_from_id', 'af.name as account_from_name', 'af.config_type as account_from_type',
                'd.account_to_id', 'at.name as account_to_name', 'at.config_type as account_to_type'
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
        $this->info("Found {$count} suspicious transfer(s)");

        if ($count === 0) {
            return 0;
        }

        // Print a sample table (first 20)
        $sample = $rows->take(20)->map(function ($r) {
            return [
                'id' => $r->id,
                'date' => $r->date,
                'user_id' => $r->user_id,
                'from_id' => $r->account_from_id,
                'from_name' => $r->account_from_name,
                'from_type' => $r->account_from_type,
                'to_id' => $r->account_to_id,
                'to_name' => $r->account_to_name,
                'to_type' => $r->account_to_type,
            ];
        })->toArray();

        $this->table(
            ['id','date','user_id','from_id','from_name','from_type','to_id','to_name','to_type'],
            $sample
        );

        if ($this->option('export')) {
            $ts = now()->format('Ymd_His');
            $path = "reports/transfer_issues_{$ts}.json";
            Storage::disk('local')->put($path, json_encode($rows, JSON_PRETTY_PRINT));
            $this->info("Exported full report to storage/app/{$path}");
        }

        return 0;
    }
}
