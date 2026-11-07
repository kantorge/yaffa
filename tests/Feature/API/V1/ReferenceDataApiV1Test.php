<?php

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Investment;
use Tests\Concerns\CreatesTestTransactions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class, CreatesTestTransactions::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['*']);
});

it('returns the user\'s accounts, payees, categories, tags and currencies, and nobody else\'s', function () {
    $account = AccountEntity::factory()->asAccount($this->user)->create();
    $payee = AccountEntity::factory()->asPayee($this->user)->create();
    $category = Category::factory()->for($this->user)->create();
    $tag = Tag::factory()->for($this->user)->create();
    $other = User::factory()->create();
    $foreignCategory = Category::factory()->for($other)->create();
    $foreignPayee = AccountEntity::factory()->asPayee($other)->create();

    $response = $this->getJson(route('api.v1.reference-data'))->assertOk();

    expect(collect($response->json('accounts'))->pluck('id')->all())->toContain($account->id);
    expect(collect($response->json('payees'))->pluck('id')->all())->toContain($payee->id)->not->toContain($foreignPayee->id);
    expect(collect($response->json('categories'))->pluck('id')->all())->toContain($category->id)->not->toContain($foreignCategory->id);
    expect(collect($response->json('tags'))->pluck('id')->all())->toContain($tag->id);
    $response->assertJsonStructure(['currencies', 'server_time'])->assertJsonMissingPath('ids');
    expect($response->json('accounts.0'))->toHaveKeys(['id', 'name', 'active', 'currency_id', 'account_group_id']);
});

it('returns only changed rows with updated_since, plus every current id for deletion detection', function () {
    $old = Category::factory()->for($this->user)->create();
    $old->forceFill(['updated_at' => now()->subDays(5)])->saveQuietly();
    $recent = Category::factory()->for($this->user)->create();

    $response = $this->getJson(route('api.v1.reference-data', ['updated_since' => now()->subDay()->toIso8601String()]))->assertOk();

    expect(collect($response->json('categories'))->pluck('id')->all())->toContain($recent->id)->not->toContain($old->id);
    expect($response->json('ids.categories'))->toContain($old->id)->toContain($recent->id);
});

it('answers 304 when the ETag still matches', function () {
    Category::factory()->for($this->user)->create();

    $first = $this->getJson(route('api.v1.reference-data'))->assertOk();
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    $this->getJson(route('api.v1.reference-data'), ['If-None-Match' => $etag])->assertStatus(304);

    Category::factory()->for($this->user)->create();
    $this->getJson(route('api.v1.reference-data'), ['If-None-Match' => $etag])->assertOk();
});

it('adds alias, the payee default category and investments without dropping existing keys', function () {
    $category = Category::factory()->for($this->user)->create();
    $account = AccountEntity::factory()->asAccount($this->user)->create(['alias' => 'main']);
    $payee = AccountEntity::factory()->asPayee($this->user)->create(['alias' => 'shop']);
    $payee->config->update(['category_id' => $category->id]);
    $investment = Investment::factory()->for($this->user)->create();

    $response = $this->getJson(route('api.v1.reference-data'))->assertOk();

    $accountRow = collect($response->json('accounts'))->firstWhere('id', $account->id);
    expect($accountRow)->toHaveKeys(['id', 'name', 'active', 'currency_id', 'account_group_id', 'alias'])
        ->and($accountRow['alias'])->toBe('main');
    $payeeRow = collect($response->json('payees'))->firstWhere('id', $payee->id);
    expect($payeeRow)->toMatchArray(['alias' => 'shop', 'category_id' => $category->id]);
    expect(collect($response->json('investments'))->firstWhere('id', $investment->id))
        ->toHaveKeys(['id', 'name', 'symbol', 'currency_id', 'active']);
});

it('aggregates recent usage per user, ignoring old and foreign transactions', function () {
    $account = $this->createAccountEntity($this->user);
    $payee = $this->createPayeeEntity($this->user);
    $categoryA = Category::factory()->for($this->user)->create();
    $categoryB = Category::factory()->for($this->user)->create();

    $this->createTransactionWithCategory($this->user, $account->id, $payee->id, $categoryA->id, now()->subDays(3));
    $this->createTransactionWithCategory($this->user, $account->id, $payee->id, $categoryA->id, now()->subDay());
    $this->createTransactionWithCategory($this->user, $account->id, $payee->id, $categoryB->id, now()->subDay());
    $this->createTransactionWithCategory($this->user, $account->id, $payee->id, $categoryB->id, now()->subMonths(14));

    $other = User::factory()->create();
    $this->createStandardTransaction($other, $this->createAccountEntity($other)->id, null, 5, now());

    $response = $this->getJson(route('api.v1.reference-data.usage'))->assertOk();

    expect($response->json('window_months'))->toBe(12);
    expect($response->json('standard'))->toHaveCount(1);
    expect($response->json('standard.0'))->toMatchArray([
        'type' => 'withdrawal',
        'account_from_id' => $account->id,
        'account_to_id' => $payee->id,
        'uses' => 3,
        'last_used' => now()->subDay()->toDateString(),
    ]);

    $categories = collect($response->json('payee_categories'))->keyBy('category_id');
    expect($categories[$categoryA->id]['uses'])->toBe(2)
        ->and($categories[$categoryB->id]['uses'])->toBe(1);
});
