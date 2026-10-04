<?php

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function templateTestFixtures(User $user): array
{
    return [
        'account' => AccountEntity::factory()->asAccount($user)->create(['active' => true]),
        'payee' => AccountEntity::factory()->asPayee($user)->create(['active' => true]),
        'category' => Category::factory()->for($user)->create(['active' => true]),
    ];
}

function templateWithdrawalDraft(array $fixtures, array $overrides = []): array
{
    return array_replace_recursive([
        'config_type' => 'standard',
        'transaction_type' => 'withdrawal',
        'config' => [
            'account_from_id' => $fixtures['account']->id,
            'account_to_id' => $fixtures['payee']->id,
            'amount_from' => '1500.00',
            'amount_to' => '1500.00',
        ],
        'transaction_items' => [['category_id' => $fixtures['category']->id, 'amount' => '1500.00']],
    ], $overrides);
}

it('creates, reads, updates and deletes a template', function () {
    $user = User::factory()->create();
    $fixtures = templateTestFixtures($user);
    $this->actingAs($user);

    $id = $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'Parking',
        'is_featured' => true,
        'draft' => templateWithdrawalDraft($fixtures),
    ])->assertCreated()
        ->assertJsonPath('template.name', 'Parking')
        ->assertJsonPath('template.payee_id', $fixtures['payee']->id)
        ->json('template.id');

    $this->getJson(route('api.v1.transaction-templates.show', $id))
        ->assertOk()
        ->assertJsonPath('draft.schema_version', 2)
        ->assertJsonPath('draft.config.amount_from', '1500.00')
        ->assertJsonPath('draft.matched_entities.payee.id', $fixtures['payee']->id)
        ->assertJsonPath('notices', []);

    $this->patchJson(route('api.v1.transaction-templates.update', $id), ['is_featured' => false])
        ->assertOk()
        ->assertJsonPath('template.is_featured', false);
    expect(TransactionTemplate::find($id)->draft['config']['account_to_id'])->toBe($fixtures['payee']->id);

    $this->deleteJson(route('api.v1.transaction-templates.destroy', $id))->assertOk();
    $this->assertDatabaseMissing('transaction_templates', ['id' => $id]);
});

it('does not expose or change another user\'s template', function () {
    $template = TransactionTemplate::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.v1.transaction-templates.show', $template))->assertForbidden();
    $this->patchJson(route('api.v1.transaction-templates.update', $template), ['name' => 'Mine'])->assertForbidden();
    $this->deleteJson(route('api.v1.transaction-templates.destroy', $template))->assertForbidden();
    $this->getJson(route('api.v1.transaction-templates.index'))->assertOk()->assertJsonCount(0);
});

it('rejects a date or raw data in the draft, and a duplicate name', function () {
    $user = User::factory()->create();
    $fixtures = templateTestFixtures($user);
    TransactionTemplate::factory()->for($user)->create(['name' => 'Parking']);
    $this->actingAs($user);

    $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'With date', 'draft' => templateWithdrawalDraft($fixtures, ['date' => '2026-10-01']),
    ])->assertUnprocessable()->assertJsonValidationErrors('draft.date');

    $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'With raw', 'draft' => templateWithdrawalDraft($fixtures, ['raw' => ['a' => 1]]),
    ])->assertUnprocessable()->assertJsonValidationErrors('draft.raw');

    $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'Parking', 'draft' => templateWithdrawalDraft($fixtures),
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('allows the same template name for different users', function () {
    TransactionTemplate::factory()->create(['name' => 'Parking']);
    $this->actingAs(User::factory()->create());

    $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'Parking', 'draft' => ['config_type' => 'standard'],
    ])->assertCreated();
});

it('derives the payee from the draft and ignores a client-sent payee_id', function () {
    $user = User::factory()->create();
    $fixtures = templateTestFixtures($user);
    $otherPayee = AccountEntity::factory()->asPayee($user)->create();
    $this->actingAs($user);

    $deposit = $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'Salary',
        'payee_id' => $otherPayee->id,
        'draft' => [
            'config_type' => 'standard',
            'transaction_type' => 'deposit',
            'config' => ['account_from_id' => $fixtures['payee']->id, 'account_to_id' => $fixtures['account']->id],
        ],
    ])->assertCreated()->assertJsonPath('template.payee_id', $fixtures['payee']->id);

    $transfer = $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'Savings',
        'payee_id' => $otherPayee->id,
        'draft' => [
            'config_type' => 'standard',
            'transaction_type' => 'transfer',
            'config' => ['account_from_id' => $fixtures['account']->id],
        ],
    ])->assertCreated()->assertJsonPath('template.payee_id', null);

    expect($deposit->json('template.id'))->not->toBe($transfer->json('template.id'));
});

it('keeps the template when its payee is deleted', function () {
    $user = User::factory()->create();
    $fixtures = templateTestFixtures($user);
    $this->actingAs($user);

    $id = $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'Parking', 'draft' => templateWithdrawalDraft($fixtures),
    ])->json('template.id');

    // Force a hard delete of the payee row, bypassing application-level guards
    AccountEntity::query()->whereKey($fixtures['payee']->id)->delete();

    expect(TransactionTemplate::find($id)->payee_id)->toBeNull();
});

it('blanks stale references and returns notices', function () {
    $user = User::factory()->create();
    $fixtures = templateTestFixtures($user);
    $this->actingAs($user);

    $id = $this->postJson(route('api.v1.transaction-templates.store'), [
        'name' => 'Parking', 'draft' => templateWithdrawalDraft($fixtures),
    ])->json('template.id');

    $fixtures['account']->update(['active' => false]);

    $response = $this->getJson(route('api.v1.transaction-templates.show', $id))->assertOk();

    expect($response->json('draft.config'))->not->toHaveKey('account_from_id')
        ->and($response->json('notices'))->toBe([['field' => 'config.account_from_id', 'reason' => 'inactive']]);
});

it('lists featured templates, most used first, with a limit', function () {
    $user = User::factory()->create();
    TransactionTemplate::factory()->for($user)->create(['name' => 'A rare', 'is_featured' => true, 'use_count' => 1]);
    TransactionTemplate::factory()->for($user)->create(['name' => 'B often', 'is_featured' => true, 'use_count' => 9]);
    TransactionTemplate::factory()->for($user)->create(['name' => 'C hidden', 'is_featured' => false, 'use_count' => 99]);
    $this->actingAs($user);

    expect($this->getJson(route('api.v1.transaction-templates.index', ['featured' => 1]))->json('*.name'))
        ->toBe(['B often', 'A rare']);
    expect($this->getJson(route('api.v1.transaction-templates.index', ['limit' => 1]))->json('*.name'))
        ->toBe(['C hidden']);
});

it('bumps the usage statistics when a transaction is stored from a template', function () {
    $user = User::factory()->create();
    $fixtures = templateTestFixtures($user);
    $template = TransactionTemplate::factory()->for($user)->create();
    $this->actingAs($user);

    $payload = [
        'action' => 'create',
        'transaction_type' => 'withdrawal',
        'config_type' => 'standard',
        'date' => '2026-10-01',
        'reconciled' => false,
        'schedule' => false,
        'config' => [
            'account_from_id' => $fixtures['account']->id,
            'account_to_id' => $fixtures['payee']->id,
            'amount_from' => 8,
            'amount_to' => 8,
        ],
        'items' => [['amount' => 8, 'category_id' => $fixtures['category']->id]],
    ];

    $this->postJson(route('api.v1.transactions.store-standard'), $payload + ['transaction_template_id' => $template->id])
        ->assertOk();

    $template->refresh();
    expect($template->use_count)->toBe(1)->and($template->last_used_at)->not->toBeNull();

    $foreign = TransactionTemplate::factory()->create();
    $this->postJson(route('api.v1.transactions.store-standard'), $payload + ['transaction_template_id' => $foreign->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('transaction_template_id');
    expect($foreign->fresh()->use_count)->toBe(0);
});

it('renders the save-as-template page for the owner', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->withdrawal($user)->create();

    $this->actingAs($user)->get(route('transaction.open', ['transaction' => $transaction, 'action' => 'template']))
        ->assertOk();
});

it('does not render the save-as-template page for another user', function () {
    $transaction = Transaction::factory()->withdrawal(User::factory()->create())->create();

    $this->actingAs(User::factory()->create())
        ->get(route('transaction.open', ['transaction' => $transaction, 'action' => 'template']))
        ->assertForbidden();
});

it('renders the template pages for the owner', function () {
    $user = User::factory()->create();
    $fixtures = templateTestFixtures($user);
    $template = TransactionTemplate::factory()->for($user)->create(['draft' => templateWithdrawalDraft($fixtures) + ['schema_version' => 2]]);

    $this->actingAs($user);
    $this->get(route('transaction-templates.index'))->assertOk();
    $this->get(route('transaction-templates.create', 'standard'))->assertOk();
    $this->get(route('transaction-templates.edit', $template))->assertOk();
});

it('does not render the edit page of another user\'s template', function () {
    $template = TransactionTemplate::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('transaction-templates.edit', $template))->assertForbidden();
});
