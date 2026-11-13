<?php

use App\Enums\CategoryTagRequirement;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\RechargeTagService;
use Laravel\Sanctum\Sanctum;

/**
 * Recharge categories: a category can require a tag (optionally expecting the tag's balance to net
 * to nil). Missing tags are reported, never blocked; tag balances count all standard items with the tag.
 */
function rechargeUser(bool $enabled = true): User
{
    $user = User::factory()->create(['recharge_tags_enabled' => $enabled]);
    AccountGroup::factory()->for($user)->create();
    Currency::factory()->for($user)->create(['base' => true]);
    AccountEntity::factory()->asAccount($user)->create();
    AccountEntity::factory()->asPayee($user)->create();

    return $user;
}

/**
 * A real (non-schedule) standard transaction with exactly one item.
 *
 * @param  array<int>  $tagIds
 */
function rechargeItem(User $user, string $type, Category $category, string $amount, array $tagIds = [], bool $schedule = false, ?string $date = null): TransactionItem
{
    $factory = Transaction::factory()->for($user);
    $factory = $type === 'deposit' ? $factory->deposit($user) : $factory->withdrawal($user);
    // make() + save() skips the factory's afterCreating hook, which adds random items and tags
    $transaction = $factory->make(array_filter(['schedule' => $schedule ?: null, 'date' => $date]));
    $transaction->save();

    $item = TransactionItem::factory()->create([
        'transaction_id' => $transaction->id,
        'category_id' => $category->id,
        'amount' => $amount,
    ]);
    $item->tags()->sync($tagIds);

    return $item;
}

it('stores the tag requirement from the category form and defaults it to none', function () {
    $user = rechargeUser();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Work recharges',
            'active' => 1,
            'default_aggregation' => 'month',
            'tag_requirement' => 'required_nil',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Groceries',
            'active' => 1,
            'default_aggregation' => 'month',
        ])
        ->assertRedirect();

    expect(Category::where('name', 'Work recharges')->first()->tag_requirement)
        ->toBe(CategoryTagRequirement::REQUIRED_NIL)
        ->and(Category::where('name', 'Groceries')->first()->tag_requirement)
        ->toBe(CategoryTagRequirement::NONE);
});

it('rejects an unknown tag requirement', function () {
    $user = rechargeUser();

    $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Odd',
            'active' => 1,
            'default_aggregation' => 'month',
            'tag_requirement' => 'always',
        ])
        ->assertSessionHasErrors('tag_requirement');
});

it('lists only untagged real items in tag-requiring categories', function () {
    $user = rechargeUser();
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required']);
    $plain = Category::factory()->for($user)->create(['tag_requirement' => 'none']);
    $tag = Tag::factory()->for($user)->create();

    $untagged = rechargeItem($user, 'withdrawal', $recharge, '12.50');
    rechargeItem($user, 'withdrawal', $recharge, '5', [$tag->id]);
    rechargeItem($user, 'withdrawal', $plain, '7');
    rechargeItem($user, 'withdrawal', $recharge, '9', [], schedule: true);

    $this->actingAs($user);
    $items = app(RechargeTagService::class)->untaggedItems($user);

    expect($items->pluck('id')->all())->toBe([$untagged->id]);

    $this->get(route('reports.recharges.untagged'))
        ->assertOk()
        ->assertSee('12.5');
});

it('saves a transaction with an untagged item in a tag-requiring category', function () {
    // The requirement is a reminder only: the API must never reject the transaction
    $user = rechargeUser();
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required_nil']);

    $item = rechargeItem($user, 'withdrawal', $recharge, '10');

    expect($item->fresh())->not->toBeNull()
        ->and(app(RechargeTagService::class)->untaggedItemCount($user))->toBe(1);
});

it('balances a tag across all standard items, settling within 0.01', function () {
    $user = rechargeUser();
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required_nil']);
    $income = Category::factory()->for($user)->create(['tag_requirement' => 'none']);
    $settled = Tag::factory()->for($user)->create(['name' => 'Claim 1']);
    $open = Tag::factory()->for($user)->create(['name' => 'Claim 2']);
    $unrelated = Tag::factory()->for($user)->create(['name' => 'Holiday']);

    // Claim 1: paid 100, reimbursed 99.995 into another category -> within tolerance
    rechargeItem($user, 'withdrawal', $recharge, '100', [$settled->id]);
    rechargeItem($user, 'deposit', $income, '99.995', [$settled->id]);
    // Claim 2: paid 40, reimbursed 25
    rechargeItem($user, 'withdrawal', $recharge, '40', [$open->id]);
    rechargeItem($user, 'deposit', $recharge, '25', [$open->id]);
    // A tag never used in an "expect nil" category is not a recharge tag
    rechargeItem($user, 'withdrawal', $income, '30', [$unrelated->id]);

    $this->actingAs($user);
    $balances = app(RechargeTagService::class)->tagBalances($user)->keyBy('id');

    expect($balances->keys()->sort()->values()->all())->toBe(collect([$settled->id, $open->id])->sort()->values()->all())
        ->and($balances[$settled->id]['settled'])->toBeTrue()
        ->and($balances[$open->id]['settled'])->toBeFalse()
        ->and($balances[$open->id]['balance'])->toBe('-15.0000')
        ->and($balances[$open->id]['item_count'])->toBe(2);

    $this->get(route('reports.recharges.open'))
        ->assertOk()
        ->assertSee('Claim 2')
        ->assertSee('Claim 1');
});

it('keeps an unsettled tag listed even when it is inactive', function () {
    $user = rechargeUser();
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required_nil']);
    $tag = Tag::factory()->for($user)->create(['active' => false]);
    rechargeItem($user, 'withdrawal', $recharge, '20', [$tag->id]);

    $this->actingAs($user);
    $row = app(RechargeTagService::class)->tagBalances($user)->first();

    expect($row['id'])->toBe($tag->id)
        ->and($row['settled'])->toBeFalse();
});

it('deactivates only settled active tags', function () {
    $user = rechargeUser();
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required_nil']);
    $settled = Tag::factory()->for($user)->create(['active' => true]);
    $open = Tag::factory()->for($user)->create(['active' => true]);
    rechargeItem($user, 'withdrawal', $recharge, '50', [$settled->id]);
    rechargeItem($user, 'deposit', $recharge, '50', [$settled->id]);
    rechargeItem($user, 'withdrawal', $recharge, '50', [$open->id]);

    // Asking for the open tag too must not deactivate it
    $this->actingAs($user)
        ->post(route('reports.recharges.deactivate-settled'), ['tag_ids' => [$settled->id, $open->id]])
        ->assertRedirect(route('reports.recharges.open'));

    expect($settled->fresh()->active)->toBeFalse()
        ->and($open->fresh()->active)->toBeTrue();
});

it('does not deactivate another user\'s settled tags', function () {
    $owner = rechargeUser();
    $recharge = Category::factory()->for($owner)->create(['tag_requirement' => 'required_nil']);
    $tag = Tag::factory()->for($owner)->create(['active' => true]);
    rechargeItem($owner, 'withdrawal', $recharge, '50', [$tag->id]);
    rechargeItem($owner, 'deposit', $recharge, '50', [$tag->id]);

    $intruder = rechargeUser();
    $this->actingAs($intruder)
        ->post(route('reports.recharges.deactivate-settled'), ['tag_ids' => [$tag->id]])
        ->assertRedirect();

    expect($tag->fresh()->active)->toBeTrue();
});

it('returns the recharge summary for the dashboard widget', function () {
    $user = rechargeUser();

    Sanctum::actingAs($user, ['read']);
    $this->getJson(route('api.v1.reports.recharges.summary'))
        ->assertOk()
        ->assertExactJson(['enabled' => false]);

    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required_nil']);
    $tag = Tag::factory()->for($user)->create();
    rechargeItem($user, 'withdrawal', $recharge, '10');
    rechargeItem($user, 'withdrawal', $recharge, '10', [$tag->id]);

    $this->getJson(route('api.v1.reports.recharges.summary'))
        ->assertOk()
        ->assertExactJson([
            'enabled' => true,
            'untagged_items' => 1,
            'open_tags' => 1,
            'settled_active_tags' => 0,
        ]);
});

it('exposes the tag requirement in reference data for the transaction form', function () {
    $user = rechargeUser();
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required']);

    $this->actingAs($user)
        ->getJson(route('api.v1.reference-data'))
        ->assertOk()
        ->assertJsonFragment(['id' => $recharge->id, 'tag_requirement' => 'required']);
});

it('hides the recharge pages and widget data while the setting is off', function () {
    $user = rechargeUser(enabled: false);
    Category::factory()->for($user)->create(['tag_requirement' => 'required_nil']);

    $this->actingAs($user)->get(route('reports.recharges.untagged'))->assertNotFound();
    $this->actingAs($user)->get(route('reports.recharges.open'))->assertNotFound();
    $this->actingAs($user)->post(route('reports.recharges.deactivate-settled'))->assertNotFound();

    Sanctum::actingAs($user, ['read']);
    $this->getJson(route('api.v1.reports.recharges.summary'))
        ->assertOk()
        ->assertExactJson(['enabled' => false]);
});

it('shows the tag requirement field on the category form while the setting is on', function () {
    $this->actingAs(rechargeUser())
        ->get(route('categories.create'))
        ->assertOk()
        ->assertSee('name="tag_requirement"', false);
});

it('hides the tag requirement field on the category form while the setting is off', function () {
    $this->actingAs(rechargeUser(enabled: false))
        ->get(route('categories.create'))
        ->assertOk()
        ->assertDontSee('name="tag_requirement"', false);
});

it('keeps the stored tag requirement of a category when the form does not send it', function () {
    // The field is not rendered while the setting is off; saving must not reset the value
    $user = rechargeUser(enabled: false);
    $category = Category::factory()->for($user)->create(['tag_requirement' => 'required_nil', 'default_aggregation' => 'month']);

    $this->actingAs($user)
        ->patch(route('categories.update', $category), [
            'name' => 'Renamed',
            'active' => 1,
            'default_aggregation' => 'month',
        ])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('Renamed')
        ->and($category->fresh()->tag_requirement)->toBe(CategoryTagRequirement::REQUIRED_NIL);
});

it('saves the recharge settings from the user settings form', function () {
    $user = rechargeUser(enabled: false);

    $this->actingAs($user)
        ->patchJson(route('api.v1.users.me.settings'), [
            'language' => 'hu',
            'locale' => 'hu-HU',
            'start_date' => '2021-01-01',
            'end_date' => '2021-12-31',
            'account_details_date_range' => 'yesterday',
            'auto_merge_standard_transaction_items' => false,
            'recharge_tags_enabled' => true,
            'recharge_backstop_date' => '2026-01-01',
        ])
        ->assertOk()
        ->assertJsonPath('data.recharge_tags_enabled', true)
        ->assertJsonPath('data.recharge_backstop_date', '2026-01-01');

    $user->refresh();
    expect($user->recharge_tags_enabled)->toBeTrue()
        ->and($user->recharge_backstop_date->format('Y-m-d'))->toBe('2026-01-01');
});

it('hides untagged items dated before the backstop date', function () {
    $user = rechargeUser();
    $user->update(['recharge_backstop_date' => '2026-03-01']);
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required']);

    rechargeItem($user, 'withdrawal', $recharge, '10', date: '2026-02-28');
    $onDate = rechargeItem($user, 'withdrawal', $recharge, '20', date: '2026-03-01');

    $this->actingAs($user);
    expect(app(RechargeTagService::class)->untaggedItems($user)->pluck('id')->all())->toBe([$onDate->id]);
});

it('hides tags whose latest item is before the backstop date, keeping the full balance of the others', function () {
    $user = rechargeUser();
    $user->update(['recharge_backstop_date' => '2026-03-01']);
    $recharge = Category::factory()->for($user)->create(['tag_requirement' => 'required_nil']);
    $old = Tag::factory()->for($user)->create();
    $spanning = Tag::factory()->for($user)->create();

    rechargeItem($user, 'withdrawal', $recharge, '30', [$old->id], date: '2026-01-15');
    // Spent before the backstop, reimbursed after it: settled, not an open balance
    rechargeItem($user, 'withdrawal', $recharge, '50', [$spanning->id], date: '2026-02-10');
    rechargeItem($user, 'deposit', $recharge, '50', [$spanning->id], date: '2026-03-05');

    $this->actingAs($user);
    $balances = app(RechargeTagService::class)->tagBalances($user);

    expect($balances->pluck('id')->all())->toBe([$spanning->id])
        ->and($balances->first()['settled'])->toBeTrue();
});
