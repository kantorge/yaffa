<?php

use App\Events\TransactionCreated;
use App\Models\AiProviderConfig;
use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\CategoryLearning;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\User;
use App\Services\CategoryLearningService;
use App\Services\PayeeProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\CreatesTestTransactions;
use Tests\Concerns\ProcessesAiDocuments;

uses(RefreshDatabase::class, CreatesTestTransactions::class, ProcessesAiDocuments::class);

beforeEach(function () {
    $this->user = User::factory()->create(['language' => 'en']);
    AiUserSettings::factory()->enabled()->create(['user_id' => $this->user->id, 'auto_record_enabled' => true]);
    AiProviderConfig::factory()->for($this->user)->create();
    $this->account = $this->createAccountEntity($this->user, ['name' => 'Visa card', 'alias' => '*1234']);
    $this->category = Category::factory()->for($this->user)->create(['active' => true]);
    $this->currency = $this->account->config->currency->iso_code;

    // A payee with $count single-item transactions (10-12, median 11) in one category, plus one in
    // another category when $mixed, so the payee has no single-category shortcut but still qualifies
    $this->payeeWithHistory = function (int $count = 12, array $config = [], bool $mixed = false) {
        $payee = $this->createPayeeEntity($this->user, ['name' => 'Parking Kft', 'active' => true]);
        $payee->config->update($config);
        foreach (range(1, $count) as $i) {
            $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 10 + ($i % 3), now()->subDays($i), categoryId: $this->category->id);
        }
        if ($mixed) {
            $this->otherCategory = Category::factory()->for($this->user)->create(['active' => true]);
            $this->createStandardTransaction($this->user, $this->account->id, $payee->id, 11, now()->subDays($count + 1), categoryId: $this->otherCategory->id);
        }
        app(PayeeProfileService::class)->calculate($payee);

        return $payee;
    };

    $this->notification = fn (array $payload = [], array $attributes = []) => $this->processDocument($this->user, $payload + [
        'document_kind' => 'bank_notification',
        'card_last_digits' => '1234',
        'currency' => $this->currency,
    ], $attributes);

    $this->receipt = fn (array $payload = []) => $this->processDocument($this->user, $payload + [
        'document_kind' => 'receipt',
        'currency' => $this->currency,
        'transaction_items' => [['description' => 'Parking', 'amount' => 11]],
    ]);
});

it('records a document for a qualifying payee as a normal transaction with an explained origin', function () {
    $payee = ($this->payeeWithHistory)();

    $document = ($this->notification)();

    expect($document->status)->toBe('auto_recorded');

    $transaction = Transaction::query()->where('ai_document_id', $document->id)->sole()->load('config', 'transactionItems');
    expect($transaction->config->account_from_id)->toBe($this->account->id)
        ->and($transaction->config->account_to_id)->toBe($payee->id)
        ->and($transaction->transactionItems)->toHaveCount(1)
        ->and($transaction->transactionItems[0]->category_id)->toBe($this->category->id)
        ->and($transaction->transactionItems[0]->amount->getAmount()->isEqualTo('11'))->toBeTrue()
        ->and($transaction->date->toDateString())->toBe(now()->subDays(30)->toDateString())
        // the TransactionCreated listener ran: the transaction is a normal one
        ->and($transaction->currency_id)->not->toBeNull();

    $origin = TransactionOrigin::query()->where('transaction_id', $transaction->id)->sole();
    expect($origin->relation)->toBe('created')
        ->and($origin->origin_id)->toBe($document->id)
        ->and($origin->reviewed_at)->toBeNull()
        ->and($origin->decision_reason)->not->toBeEmpty()
        ->and($origin->decision_reason)->toContain('1234');

    // Nobody confirmed the category, so nothing is learned
    expect(CategoryLearning::count())->toBe(0);
});

it('fires TransactionCreated when auto-recording', function () {
    Event::fake([TransactionCreated::class]);
    ($this->payeeWithHistory)();

    ($this->notification)();

    Event::assertDispatched(TransactionCreated::class, 1);
});

it('records nothing and does not mark the failure when auto-recording is off', function () {
    ($this->payeeWithHistory)();
    AiUserSettings::query()->where('user_id', $this->user->id)->update(['auto_record_enabled' => false]);

    $document = ($this->notification)();

    expect($document->status)->toBe('ready_for_review')
        ->and(Transaction::query()->where('ai_document_id', $document->id)->exists())->toBeFalse()
        ->and($document->processed_transaction_data)->not->toHaveKey('auto_record');
});

it('sends a document that fails a gate to review with the failed gate and reason', function () {
    ($this->payeeWithHistory)();

    $document = ($this->notification)(['card_last_digits' => '9999']);

    expect($document->status)->toBe('ready_for_review')
        ->and($document->processed_transaction_data['auto_record']['failed_gate'])->toBe('account')
        ->and($document->processed_transaction_data['auto_record']['reason'])->not->toBeEmpty()
        ->and(Transaction::query()->where('ai_document_id', $document->id)->exists())->toBeFalse();
});

it('holds a summary for a payee that expects itemization, and records the merged receipt with the notification account', function () {
    $payee = ($this->payeeWithHistory)(config: ['itemization_expected' => true]);

    $notification = ($this->notification)();

    expect($notification->status)->toBe('awaiting_itemization')
        ->and($notification->processed_transaction_data['auto_record']['hold_until'])->toBe(now()->addDays(14)->toDateString())
        ->and(Transaction::query()->where('ai_document_id', $notification->id)->exists())->toBeFalse();
    $countBefore = Transaction::count();

    $receipt = ($this->receipt)();

    expect($receipt->status)->toBe('auto_recorded')
        ->and($notification->fresh()->status)->toBe('duplicate')
        ->and(Transaction::count())->toBe($countBefore + 1);

    $transaction = Transaction::query()->where('ai_document_id', $receipt->id)->sole()->load('config');
    expect($transaction->config->account_from_id)->toBe($this->account->id)
        ->and($transaction->config->account_to_id)->toBe($payee->id);

    // The notification is linked to the transaction it was merged into
    $link = TransactionOrigin::query()->where('relation', 'duplicate_of')->sole();
    expect($link->transaction_id)->toBe($transaction->id)
        ->and($link->origin_id)->toBe($notification->id);
});

it('moves a held document to review only after the timeout', function () {
    ($this->payeeWithHistory)(config: ['itemization_expected' => true]);
    $young = ($this->notification)();
    $old = ($this->notification)(['date' => now()->subDays(40)->toDateString()]);
    $young->forceFill(['status_changed_at' => now()->subDays(13)])->save();
    $old->forceFill(['status_changed_at' => now()->subDays(15)])->save();

    $this->artisan('ai-documents:expire-itemization')->assertSuccessful();

    expect($young->fresh()->status)->toBe('awaiting_itemization')
        ->and($old->fresh()->status)->toBe('ready_for_review')
        ->and($old->fresh()->processed_transaction_data['auto_record']['reason'])->toContain('14');
});

it('leaves an auto-recorded transaction untouched and lists the conflict when a receipt contradicts it', function () {
    ($this->payeeWithHistory)(count: 20, mixed: true);
    $learning = new CategoryLearningService($this->user);
    $learning->recordCategorySelection('Parking fee', $this->category->id);
    $learning->recordCategorySelection('Coffee', $this->otherCategory->id);

    $notification = ($this->notification)();
    expect($notification->status)->toBe('auto_recorded');
    $transaction = Transaction::query()->where('ai_document_id', $notification->id)->sole();
    $before = [$transaction->fresh()->toArray(), $transaction->transactionItems()->get()->toArray()];

    $receipt = ($this->receipt)(['transaction_items' => [
        ['description' => 'Parking fee', 'amount' => 6],
        ['description' => 'Coffee', 'amount' => 5],
    ]]);

    expect($receipt->status)->toBe('ready_for_review');
    $conflict = TransactionOrigin::query()->where('relation', 'conflicts_with')->sole();
    expect($conflict->transaction_id)->toBe($transaction->id)
        ->and($conflict->origin_id)->toBe($receipt->id)
        ->and([$transaction->fresh()->toArray(), $transaction->transactionItems()->get()->toArray()])->toEqual($before);
});

it('closes a receipt that matches an auto-recorded transaction as a duplicate', function () {
    ($this->payeeWithHistory)(count: 20, mixed: true);
    (new CategoryLearningService($this->user))->recordCategorySelection('Parking', $this->category->id);

    $notification = ($this->notification)();
    $receipt = ($this->receipt)();

    expect($receipt->status)->toBe('duplicate')
        ->and(Transaction::query()->where('ai_document_id', $notification->id)->count())->toBe(1)
        ->and(TransactionOrigin::query()->where('relation', 'duplicate_of')->sole()->origin_id)->toBe($receipt->id)
        ->and(TransactionOrigin::query()->where('relation', 'conflicts_with')->exists())->toBeFalse();
});

it('records one purchase once when its notification and receipt are processed back to back', function () {
    ($this->payeeWithHistory)();
    $countBefore = Transaction::count();

    $notification = ($this->notification)();
    $receipt = ($this->receipt)();

    expect(Transaction::count())->toBe($countBefore + 1)
        ->and($notification->status)->toBe('auto_recorded')
        ->and($receipt->status)->toBe('duplicate');
});

it('sends the "recorded automatically" variant of the processed notification', function () {
    Illuminate\Support\Facades\Mail::fake();
    ($this->payeeWithHistory)();
    $document = ($this->notification)();

    (new App\Listeners\SendAiDocumentProcessedNotification())->handle(new App\Events\AiDocumentProcessedEvent($document));

    Illuminate\Support\Facades\Mail::assertSent(
        App\Mail\AiDocumentProcessed::class,
        fn ($mail) => $mail->envelope()->subject === __('mail.ai_document_processed.subject_auto_recorded')
            && str_contains($mail->render(), __('mail.ai_document_processed.intro_auto_recorded')),
    );
});
