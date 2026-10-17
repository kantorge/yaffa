<?php

use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['language' => 'en']);
    Sanctum::actingAs($this->user, ['*']);

    $this->standardPayload = function (AiDocument $aiDocument): array {
        $account = AccountEntity::factory()->asAccount($this->user)->create(['active' => true]);
        $payee = AccountEntity::factory()->asPayee($this->user)->create(['active' => true]);
        $category = Category::factory()->for($this->user)->create(['active' => true]);

        return [
            'action' => 'finalize',
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'config' => [
                'account_from_id' => $account->id,
                'account_to_id' => $payee->id,
                'amount_from' => 8,
                'amount_to' => 8,
            ],
            'items' => [
                ['amount' => 8, 'category_id' => $category->id, 'tags' => []],
            ],
            'ai_document_id' => $aiDocument->id,
        ];
    };

    $this->investmentPayload = function (AiDocument $aiDocument): array {
        $currency = $this->user->currencies()->first() ?: Currency::factory()->for($this->user)->create();
        $investmentGroup = $this->user->investmentGroups()->first() ?: InvestmentGroup::factory()->for($this->user)->create();
        $investment = Investment::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);
        $account = AccountEntity::factory()->asAccount($this->user)->create(['active' => true]);

        return [
            'action' => 'finalize',
            'transaction_type' => 'buy',
            'config_type' => 'investment',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'config' => [
                'account_id' => $account->id,
                'investment_id' => $investment->id,
                'price' => 12.5,
                'quantity' => 2,
                'commission' => 0,
                'tax' => 0,
            ],
            'ai_document_id' => $aiDocument->id,
        ];
    };
});

dataset('store endpoints', [
    'standard' => ['api.v1.transactions.store-standard', 'standardPayload'],
    'investment' => ['api.v1.transactions.store-investment', 'investmentPayload'],
]);

it('rejects finalizing a document that is not ready for review, without creating a transaction', function (string $route, string $payloadBuilder, string $status) {
    // E.g. a document reset by a reprocess after the user opened the finalize form
    $aiDocument = AiDocument::factory()->for($this->user)->create(['status' => $status]);

    $this
        ->postJson(route($route), ($this->{$payloadBuilder})($aiDocument))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'ai_document_id' => 'Document cannot be finalized from current status',
        ]);

    $this->assertDatabaseCount('transactions', 0);
    expect($aiDocument->fresh()->status)->toBe($status);
})->with('store endpoints')->with(['ready_for_processing', 'processing', 'processing_failed']);

it('still finalizes a document that is ready for review', function (string $route, string $payloadBuilder) {
    $aiDocument = AiDocument::factory()->for($this->user)->create([
        'status' => 'ready_for_review',
        'processed_at' => null,
    ]);

    $response = $this
        ->postJson(route($route), ($this->{$payloadBuilder})($aiDocument))
        ->assertOk();

    $this->assertDatabaseHas('transactions', [
        'id' => $response->json('transaction.id'),
        'ai_document_id' => $aiDocument->id,
    ]);
    $aiDocument->refresh();
    expect($aiDocument->status)->toBe('finalized')
        ->and($aiDocument->processed_at)->not->toBeNull();
})->with('store endpoints');
