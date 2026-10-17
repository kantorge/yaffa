<?php

namespace App\Console\Commands;

use App\Casts\MoneyCast;
use App\Enums\AiDocumentStatus;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\TransactionOrigin;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use App\Services\CategoryLearningService;
use App\Services\InvestmentPriceProviderRegistry;
use App\Services\SandboxDemoDataExporter;
use App\Services\TransactionDraftService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

#[Signature('app:sandbox:reset-database {--skip-date-adjustment : Skip adjusting dates in the database} {--force-sandbox : Allow running this command even if sandbox mode is not enabled}')]
#[Description('Reset the demo (sandbox) database to an initial state to remove visitor modifications')]
class ResetDemoDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // This command cannot be run if sandbox mode is not enabled
        if ((! config('yaffa.sandbox_mode')) && (! $this->option('force-sandbox'))) {
            $this->error('This command can only be run in sandbox mode.');
            return Command::FAILURE;
        }

        $this->info('Putting site in maintenance mode...');
        Artisan::call('down');

        // As part of the reset process, we also need to clear all caches to avoid any issues with stale data after the reset.
        $this->info('Clearing caches...');
        Artisan::call('cache:clear');

        // Additionally, remove the AI Document files from storage. We can safely remove all files
        $this->info('Removing AI Document files from storage...');
        $file = new Filesystem();
        $file->cleanDirectory(storage_path('app/ai_documents'));

        // Actually rebuild the database
        $this->info('Resetting database...');
        Artisan::call('migrate:fresh', ['--force' => true]);

        // Create the demo user without using factory, which is not autoloaded in production
        $this->info('Creating demo user...');
        $demoUser = User::create([
            'id' => 1,
            'name' => 'Demo User',
            'email' => 'demo@yaffa.cc',
            'password' => Hash::make('demo'),
            'language' => 'en',
            'locale' => 'en-US',
            'start_date' => Carbon::now()->subYears(1)->startOfYear(),
            'end_date' => Carbon::now()->addYears(30)->endOfYear(),
            'auto_merge_standard_transaction_items' => true,
        ]);
        $demoUser->markEmailAsVerified();
        app(AiUserSettingsResolver::class)->getOrCreateForUser($demoUser);
        // Auto-recording is opt-in; the demo data (payees with history, card endings) is built to show it.
        // It only acts when AI processing is enabled below, which needs an AI key.
        app(AiUserSettingsResolver::class)->updateForUser($demoUser, ['auto_record_enabled' => true]);

        // There are assets in the database for a test user with ID 2
        $testUser = User::create([
            'id' => 2,
            'name' => 'Test User',
            'email' => 'test@yaffa.cc',
            'password' => Hash::make('test'),
            'language' => 'en',
            'locale' => 'en-US',
            'email_verified_at' => Carbon::now(),
        ]);
        $testUser->markEmailAsVerified();
        app(AiUserSettingsResolver::class)->getOrCreateForUser($testUser);

        // Now we need to load the demo.sql file into the database.
        // We assume the database to be empty in terms of users and user related data, except the demo user (1).
        // demo.sql wraps its own INSERT statements in START TRANSACTION/COMMIT and toggles
        // FOREIGN_KEY_CHECKS itself; if a statement fails partway through, the ROLLBACK and
        // re-enabled checks below undo the transaction and restore the session state, since the
        // trailing COMMIT/SET FOREIGN_KEY_CHECKS=1 in the file itself would otherwise never run.
        $this->info('Loading demo data from file...');
        $file = base_path('database/seeders/demo.sql');
        try {
            DB::unprepared(file_get_contents($file));
        } catch (Throwable $exception) {
            DB::unprepared('ROLLBACK; SET FOREIGN_KEY_CHECKS=1;');
            throw $exception;
        }
        $this->info('Demo data loaded.');

        // Create AI Provider Config for demo user, if provided
        if (config('demo.ai_api_key')) {
            // Ensure we don't have multiple configs for the demo user, until this constraint is active
            $demoUser->aiProviderConfigs()->delete();
            $demoUser->aiProviderConfigs()->create([
                'provider' => config('demo.ai_provider', 'openai'),
                'model' => config('demo.ai_model', 'gpt-4o-mini'),
                'api_key' => config('demo.ai_api_key'),
                'vision_enabled' => true,
            ]);

            // We also need to update or create the AI settings for the demo user, to make sure AI features are enabled
            app(AiUserSettingsResolver::class)->updateForUser($demoUser, [
                'ai_enabled' => true,
            ]);

            $this->info('AI Provider Config created.');

            // Create a set of sample received mails for the demo user, which can be used to test the email processing features of the app.
            // These will be raw incoming mails, which will have to be processed before they can be finalized as a transaction. This allows testing the full flow of incoming mail processing, including the AI parsing features.
            // Note, that there can be other AI Document records in the demo.sql file, but IDs should not conflict
            $this->info('Creating sample received mails...');
            $this->createSampleReceivedMails();
        } else {
            $this->warn('Skipping AI Provider Config - DEMO_AI_API_KEY not set');
        }

        // Create Investment Provider Config for demo user, if provided
        if (config('demo.investment_provider_key')) {
            $providerKey = (string) config('demo.investment_provider_key');

            if (! app(InvestmentPriceProviderRegistry::class)->has($providerKey)) {
                $this->warn("Skipping Investment Provider Config - unknown provider key: {$providerKey}");
            } else {
                $credentials = [];

                if (config('demo.investment_provider_credentials')) {
                    $decodedCredentials = json_decode((string) config('demo.investment_provider_credentials'), true);
                    if (! is_array($decodedCredentials)) {
                        $this->warn('Skipping Investment Provider Config - DEMO_INVESTMENT_PROVIDER_CREDENTIALS must be valid JSON object');
                    } else {
                        $credentials = $decodedCredentials;
                        $this->info('Using JSON credentials format from DEMO_INVESTMENT_PROVIDER_CREDENTIALS.');
                    }
                } elseif (config('demo.investment_provider_api_key')) {
                    $credentials = [
                        'api_key' => config('demo.investment_provider_api_key'),
                    ];
                    $this->info('Using API key shortcut format from DEMO_INVESTMENT_PROVIDER_API_KEY.');
                }

                if ($credentials !== []) {
                    $demoUser->investmentProviderConfigs()->updateOrCreate(
                        ['provider_key' => $providerKey],
                        [
                            'credentials' => $credentials,
                            'enabled' => (bool) config('demo.investment_provider_enabled', true),
                        ]
                    );

                    $this->info("Investment Provider Config created for {$providerKey}.");
                } else {
                    $this->warn('Skipping Investment Provider Config - no credentials provided');
                }
            }
        } else {
            $this->warn('Skipping Investment Provider Config - DEMO_INVESTMENT_PROVIDER_KEY not set');
        }

        // Create Google Drive Config for demo user, if provided
        if (config('demo.google_drive_json_key_file')) {
            if (file_exists(config('demo.google_drive_json_key_file'))) {
                $demoUser->googleDriveConfigs()->delete();

                $keyFileContent = file_get_contents(config('demo.google_drive_json_key_file'));
                $credentials = json_decode($keyFileContent, true);
                $demoUser->googleDriveConfigs()->create([
                    'service_account_email' => $credentials['client_email'] ?? null,
                    'folder_id' => config('demo.google_drive_folder_id'),
                    'service_account_json' => $keyFileContent,
                ]);
                $this->info('Google Drive Config created.');
            } else {
                $this->warn('Skipping Google Drive Config - provided key file not found');
            }
        } else {
            $this->warn('Skipping Google Drive Config - DEMO_GOOGLE_DRIVE_JSON_KEY_FILE not set');
        }

        /**
         * Unless explicitly disabled, we need to adjust ALL dates in the database to be the current date.
         * Created_at and updated_at fields are ignored, as it is not used at the moment by the app.
         * The shift amount and the affected tables/columns are shared with the sandbox dump/promote
         * commands via config('demo.seed_date_shift_columns'), so both directions stay in sync.
         */
        if (! $this->option('skip-date-adjustment')) {
            $this->info('Adjusting dates in the database...');
            $exporter = app(SandboxDemoDataExporter::class);
            $diffMonths = $exporter->diffMonths();
            $this->info("Months used for difference: {$diffMonths}");

            $exporter->shiftDates($diffMonths);
            $this->info('Dates adjusted.');
        }

        $this->info('Creating AI document duplicate scenario...');
        $this->createDuplicateAiDocumentScenario($demoUser);

        $this->info('Creating auto-recording scenarios...');
        $this->createAutoRecordScenarios($demoUser);

        // Initiate processing the AI documents we added
        $this->info('Initiating AI document processing...');
        Artisan::call('app:process-ai-documents');

        // Next, run automated data retrieval commands to populate the database with current data.
        $this->info('Retrieving investment data...');
        Artisan::call('app:investment-prices:get');

        // Next, run the commands to recalculate various stored data
        Artisan::call('app:cache:transaction-schedule-active-flags');
        Artisan::call('app:calculate-transaction-cached-data');
        Artisan::call('app:cache:account-monthly-summaries');
        // Payee profiles are what auto-recording decides on, and loading demo.sql fires no transaction events
        Artisan::call('app:payees:recalculate-profiles');

        // Finally, put the site live
        $this->info('Database refresh ready, putting site live...');
        Artisan::call('up');

        return Command::SUCCESS;
    }

    private function createSampleReceivedMails(): void
    {
        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - HTML and Text',
            'text' => 'This is a sample plain text body of the email.',
            'html' => '<p>This is a sample <strong>HTML</strong> body of the email.</p>',
        ]);

        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Text Only',
            'text' => 'This is a sample plain text body of the email without HTML version.',
            'html' => '',
        ]);

        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - HTML Only',
            'text' => '',
            'html' => '<p>This is a sample <strong>HTML</strong> body of the email without plain text version.</p>',
        ]);

        // TODO: read account and payee from actual demo assets
        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Easy to Process by AI',
            'text' => 'Date: ' . Carbon::now()->format('Y-m-d') . "\n" .
                'Amount: 123.45 USD' . "\n" .
                'Account: Bank Account - John' . "\n" .
                'Payee: AquaFlow Utilities' . "\n",
            'html' => '',
        ]);

        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Easy to Process by AI with known and unknown item categories',
            'text' => 'Total amount 100 USD, paid with "Credit Card - John" at "DIY Depot" on 2026-02-01.' . "\n" .
                'The amount is for the following items:' . "\n" .
                '- Hammer: 25 USD' . "\n" .  // Should be categorizes as Household / Generic household equipment (learning available)
                '- Coca Cola .5 litres: 5 USD' . "\n" . // Ideally, this should be categorized as Food / Beverages (no learning available)
                '- Absolutely Unknown Item 1: 30 USD' . "\n" .
                '- Absolutely Unknown Item 2: 40 USD' . "\n",
            'html' => '',
        ]);

        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Deposit',
            'text' => 'Total amount of 100 USD was added to "Bank Account - John" on 2026-02-01 by "NetConnect Solutions" as "Incomes / Net wage".',
            'html' => '',
        ]);

        // TODO: read account and investment details from actual demo assets
        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Simple Investment Buy Transaction',
            'text' => 'Bought 10 shares of "Disney" shares for 150 USD total (15 USD per share) on 2026-02-01 from "Brokerage account". Paid 5 USD commission fee.',
            'html' => '',
        ]);
        $this->createSampleReceivedMailsForDemoUser([
            'subject' => 'Sample Incoming Email - Simple Investment Dividend Transaction',
            'text' => 'Received 10 USD dividend from "Disney" shares on 2026-02-02 in "Brokerage account". Paid 2 USD taxes.',
            'html' => '',
        ]);
    }

    private function createSampleReceivedMailsForDemoUser(array $mailParams): void
    {
        Artisan::call('app:simulate-incoming-email', [
            '--from' => 'demo@yaffa.cc',
            '--subject' => $mailParams['subject'] ?? 'Sample Incoming Email',
            '--text' => $mailParams['text'] ?? null,
            '--html' => $mailParams['html'] ?? null,
            '--message-id' => $mailParams['message_id'] ?? 'sample-email-' . uniqid(),
            '--user-id' => 1,
        ]);
    }

    /**
     * Documents, origins and learned item categories that go with the auto-recording payees, templates and
     * transactions of demo.sql (transaction IDs 30201-30204). They are built here rather than in demo.sql,
     * because their drafts carry dates, which the date shift only reaches in real date columns.
     * Run after the date shift.
     */
    private function createAutoRecordScenarios(User $demoUser): void
    {
        $transactions = Transaction::query()
            ->where('user_id', $demoUser->id)
            ->whereIn('id', [30201, 30202, 30203, 30204])
            ->with('config')
            ->get()
            ->keyBy('id');

        if ($transactions->count() !== 4) {
            $this->warn('Skipping auto-recording scenarios - demo transactions not found');
            return;
        }

        // Exact item category matches, so a receipt from the demo grocers can be recorded without the AI guessing
        $learning = new CategoryLearningService($demoUser);
        foreach ([['sourdough bread', 115], ['orange juice', 114], ['olive oil', 110], ['potato chips', 116], ['dish soap', 34], ['tomatoes', 110]] as [$description, $categoryId]) {
            $learning->recordCategorySelection($description, $categoryId);
        }

        $names = [
            'harbor' => 'Harbor Parking',
            'north' => 'Northside Petroleum',
            'green' => 'GreenLeaf Market',
            'parknow' => 'ParkNow Garage',
            'corner' => 'Corner Fresh Grocers',
            'lake' => 'Lakeside Gas',
            'city' => 'City Parking Services',
        ];
        $payeeIds = AccountEntity::query()->where('user_id', $demoUser->id)->whereIn('name', array_values($names))->pluck('id', 'name');

        $draft = fn (string $payee, string $date, string $amount, int $accountId, array $items, array $raw = []): array => [
            'schema_version' => TransactionDraftService::SCHEMA_VERSION,
            'raw' => $raw + [
                'payee' => $names[$payee],
                'date' => $date,
                'amount' => $amount,
                'currency' => 'USD',
                'transaction_type' => 'withdrawal',
            ],
            'date' => $date,
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'config' => [
                'amount_from' => $amount,
                'amount_to' => $amount,
                'account_from_id' => $accountId,
                'account_to_id' => $payeeIds[$names[$payee]] ?? null,
            ],
            'transaction_items' => array_map(fn (array $item) => [
                'amount' => $item[1],
                'description' => $item[0],
                'recommended_category_id' => $item[2],
                'match_type' => 'exact',
                'confidence_score' => 1.0,
            ], $items),
        ];

        $document = function (string $status, string $kind, array $processed, ?Carbon $changedAt = null) use ($demoUser): AiDocument {
            /** @var AiDocument $document */
            $document = $demoUser->aiDocuments()->create([
                'status' => $status,
                'source_type' => 'manual_upload',
                'processed_transaction_data' => $processed,
                'document_kind' => $kind,
                'processed_at' => now(),
            ]);
            if ($changedAt !== null) {
                $document->forceFill(['status_changed_at' => $changedAt])->save();
            }

            return $document;
        };

        // Automatically recorded transactions, waiting for the user's confirmation. T = [transaction, payee, card, time, reason]
        $recorded = [
            30201 => ['harbor', null, '07:48', 'Recorded automatically: 12 of 12 transactions are single item in the usual category (lower bound 0.85). account from the template "Harbor Parking". Amount within the usual range.'],
            30202 => ['north', '5678', '18:05', 'Recorded automatically: 12 of 12 transactions are single item in the usual category (lower bound 0.85). account from card ending 5678. Amount within the usual range.'],
            30203 => ['green', '5678', '12:20', 'Recorded automatically: 12 of 12 transactions are single item in the usual category (lower bound 0.85). account from card ending 5678. Amount within the usual range.'],
            30204 => ['parknow', '1234', '09:15', 'Recorded automatically: Auto-recording is always allowed for this payee. account from card ending 1234. Amount within the usual range.'],
        ];

        $createdDocuments = [];
        foreach ($recorded as $transactionId => [$payee, $card, $time, $reason]) {
            /** @var Transaction $transaction */
            $transaction = $transactions[$transactionId];
            /** @var TransactionDetailStandard $config */
            $config = $transaction->config;
            $amount = (string) $config->amount_from->getAmount()->toScale(2);
            /** @var TransactionItem|null $item */
            $item = $transaction->transactionItems()->first();

            $doc = $document(AiDocumentStatus::AutoRecorded->value, 'bank_notification', $draft(
                $payee,
                $transaction->date->toDateString(),
                $amount,
                (int) $config->account_from_id,
                [[$names[$payee], $amount, $item?->category_id]],
                ['document_kind' => 'bank_notification', 'card_last_digits' => $card, 'transaction_time' => $time],
            ));

            $transaction->forceFill(['ai_document_id' => $doc->id])->saveQuietly();
            $origin = TransactionOrigin::record($demoUser, $transaction, TransactionOrigin::RELATION_CREATED, $doc, $reason);
            $createdDocuments[$transactionId] = $doc;

            // One of them was confirmed already, so both review states can be seen
            if ($transactionId === 30204) {
                $origin->forceFill(['reviewed_at' => now()])->save();
            }
        }

        // The same purchase notified twice: closed as a duplicate of an auto-recorded transaction
        /** @var TransactionDetailStandard $paidConfig */
        $paidConfig = $transactions[30204]->config;
        $paid = $transactions[30204];
        $repeat = $document(AiDocumentStatus::Duplicate->value, 'bank_notification', $draft(
            'parknow',
            $paid->date->toDateString(),
            '3.00',
            (int) $paidConfig->account_from_id,
            [['ParkNow Garage', '3.00', 10]],
            ['document_kind' => 'bank_notification', 'card_last_digits' => '1234', 'transaction_time' => '09:15'],
        ));
        TransactionOrigin::record($demoUser, $paid, TransactionOrigin::RELATION_DUPLICATE_OF, $repeat, 'Same purchase as an already recorded transaction (same amount, payee and date window).');

        // A receipt that contradicts an auto-recorded transaction (one item of 14 vs three items): a conflict to resolve
        /** @var TransactionDetailStandard $groceriesConfig */
        $groceriesConfig = $transactions[30203]->config;
        $groceries = $transactions[30203];
        $receipt = $document(AiDocumentStatus::ReadyForReview->value, 'receipt', $draft(
            'green',
            $groceries->date->toDateString(),
            '14.00',
            (int) $groceriesConfig->account_from_id,
            [['Tomatoes', '7.00', 110], ['Orange juice', '4.00', 114], ['Sourdough bread', '3.00', 115]],
            ['document_kind' => 'receipt', 'transaction_time' => '12:19'],
        ));
        TransactionOrigin::record($demoUser, $groceries, TransactionOrigin::RELATION_CONFLICTS_WITH, $receipt, 'The items of this document differ from the automatically recorded transaction.');

        // Notifications of a payee that expects a receipt: one fresh, one that has waited past the timeout
        $recent = $transactions[30201]->date->copy();
        $document(AiDocumentStatus::AwaitingItemization->value, 'bank_notification', $draft(
            'corner',
            $recent->toDateString(),
            '27.00',
            10,
            [],
            ['document_kind' => 'bank_notification', 'card_last_digits' => '5678', 'transaction_time' => '17:42'],
        ) + ['auto_record' => ['failed_gate' => 'completeness', 'reason' => 'Waiting for the itemized receipt.', 'hold_until' => now()->addDays(14)->toDateString()]], now());
        $document(AiDocumentStatus::AwaitingItemization->value, 'bank_notification', $draft(
            'corner',
            $recent->copy()->subDays(18)->toDateString(),
            '19.00',
            10,
            [],
            ['document_kind' => 'bank_notification', 'card_last_digits' => '5678', 'transaction_time' => '11:03'],
        ) + ['auto_record' => ['failed_gate' => 'completeness', 'reason' => 'Waiting for the itemized receipt.', 'hold_until' => now()->subDays(1)->toDateString()]], now()->subDays(15));

        // A document that did not pass the history gate, so it waits for a person
        $document(AiDocumentStatus::ReadyForReview->value, 'bank_notification', $draft(
            'lake',
            $recent->copy()->subDays(2)->toDateString(),
            '41.00',
            9,
            [['Lakeside Gas', '41.00', 5]],
            ['document_kind' => 'bank_notification', 'card_last_digits' => '1234', 'transaction_time' => '08:30'],
        ) + ['auto_record' => ['failed_gate' => 'history', 'reason' => 'History: Needs 4 more matching transactions (lower bound 0.65, needs 0.80).']]);

        // A dismissed document, which can still be reprocessed
        $document(AiDocumentStatus::Dismissed->value, 'bank_notification', $draft(
            'city',
            $recent->copy()->subDays(5)->toDateString(),
            '2.00',
            9,
            [['City Parking Services', '2.00', 10]],
            ['document_kind' => 'bank_notification', 'card_last_digits' => '1234', 'transaction_time' => '13:10'],
        ));
    }

    private function createDuplicateAiDocumentScenario(User $demoUser): void
    {
        $transaction = Transaction::query()
            ->where('user_id', $demoUser->id)
            ->where('config_type', 'standard')
            ->whereNotNull('date')
            ->with(['config', 'transactionItems'])
            ->first();

        if (! $transaction || $transaction->transactionItems->isEmpty()) {
            $this->warn('Skipping AI document duplicate scenario - no suitable transaction found');
            return;
        }

        if (! $transaction->config instanceof TransactionDetailStandard) {
            $this->warn('Skipping AI document duplicate scenario - unsupported transaction config');
            return;
        }

        $amount = $transaction->transactionItems->sum(fn ($item) => MoneyCast::toFloat($item->amount));
        if ($amount <= 0) {
            $this->warn('Skipping AI document duplicate scenario - transaction amount is not positive');
            return;
        }

        $rawData = [
            'date' => $transaction->date?->format('Y-m-d'),
            'amount' => $amount,
            'config_type' => $transaction->config_type,
            'transaction_type' => $transaction->transaction_type->value,
            'account_from_id' => $transaction->config->account_from_id,
            'account_to_id' => $transaction->config->account_to_id,
        ];

        /** @var TransactionItem|null $firstItem */
        $firstItem = $transaction->transactionItems->first();

        $processedData = [
            'schema_version' => TransactionDraftService::SCHEMA_VERSION,
            'raw' => $rawData,
            'date' => $rawData['date'],
            'config_type' => $rawData['config_type'],
            'transaction_type' => $rawData['transaction_type'],
            'config' => [
                'amount_from' => $amount,
                'amount_to' => $amount,
                'account_from_id' => $transaction->config->account_from_id,
                'account_to_id' => $transaction->config->account_to_id,
            ],
            'transaction_items' => [
                [
                    'amount' => $amount,
                    'recommended_category_id' => $firstItem?->category_id ?: null,
                    'match_type' => null,
                    'confidence_score' => null,
                    'description' => $firstItem->comment ?? '',
                ],
            ],
        ];

        $demoUser->aiDocuments()->create([
            'status' => 'ready_for_review',
            'source_type' => 'manual_upload',
            'processed_transaction_data' => $processedData,
            'processed_at' => now(),
        ]);
    }
}
