<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnedAccount(User $user): AccountEntity
    {
        AccountGroup::factory()->for($user)->create();
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $account = Account::factory()->withUser($user)->create();

        return AccountEntity::factory()->for($user)->for($account, 'config')->create();
    }

    public function test_guest_cannot_access_budgets(): void
    {
        $this->getJson(route('api.v1.budgets.index'))->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->postJson(route('api.v1.budgets.store'), [])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_user_can_create_an_account_agnostic_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        // amount (MoneyCast) resolves currency via the user's base currency when account-agnostic.
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'account_id' => null,
            'transaction_type' => 'withdrawal',
            'amount' => 250.50,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('category_id', $category->id)
            ->assertJsonPath('account_id', null)
            ->assertJsonPath('active', true);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => null,
        ]);
    }

    public function test_user_can_create_an_account_scoped_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $accountEntity = $this->createOwnedAccount($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'account_id' => $accountEntity->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('account_id', $accountEntity->id);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'account_id' => $accountEntity->id,
        ]);
    }

    public function test_amount_must_be_a_positive_number(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 0,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * amount rejects a value exceeding budgets.amount's DECIMAL(12,4) range - same bound
     * TransactionRequest already enforces for the equivalent transaction_items.amount /
     * transaction_details_standard.amount_from/amount_to fields.
     */
    public function test_amount_exceeding_decimal_12_4_range_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100000000,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_user_cannot_use_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $otherCategory->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_user_cannot_use_a_payee_as_the_account(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $payee = AccountEntity::factory()->asPayee($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'account_id' => $payee->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['account_id']);
    }

    public function test_end_date_and_count_are_mutually_exclusive(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addYear()->toDateString(),
            'count' => 5,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['end_date', 'count']);
    }

    /**
     * Regression guard for the DoS finding fixed in ValidatesRecurrenceRule::
     * maxRecurrencePeriodsRule(): a DAILY budget with a start_date far enough in the past spans
     * thousands of periods, which made every later RecurrenceRuleService call on it (isActive()
     * on every create/update) measurably slow (reproduced at ~4s/call for a centuries-old
     * start_date). 10 years of DAILY is ~3650 periods, comfortably over the 2000-period cap.
     */
    public function test_budget_start_date_spanning_too_many_periods_is_rejected(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'DAILY',
            'interval' => 1,
            'start_date' => Carbon::now()->subYears(10)->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_transaction_type_must_be_withdrawal_or_deposit(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'transfer',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['transaction_type']);
    }

    public function test_user_can_create_a_deposit_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        // amount (MoneyCast) resolves currency via the user's base currency when account-agnostic.
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'deposit',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'start_date' => Carbon::now()->toDateString(),
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('transaction_type', 'deposit');
    }

    public function test_active_flag_cannot_be_set_by_the_client(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        // amount (MoneyCast) resolves currency via the user's base currency when account-agnostic.
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        // A rule that has already run out of occurrences: the client-sent `active: true` must be ignored.
        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'DAILY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDays(10)->toDateString(),
            'end_date' => Carbon::now()->subDay()->toDateString(),
            'active' => true,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('active', false);
    }

    public function test_user_can_view_a_single_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->getJson(route('api.v1.budgets.show', ['budget' => $budget->id]))
            ->assertOk()
            ->assertJsonPath('id', $budget->id);
    }

    public function test_user_can_update_a_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 100,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson(route('api.v1.budgets.update', ['budget' => $budget->id]), [
            'category_id' => $category->id,
            'transaction_type' => $budget->transaction_type->value,
            'amount' => 200,
            'frequency' => $budget->frequency,
            'interval' => $budget->interval,
            'start_date' => $budget->start_date->toDateString(),
        ]);

        $response->assertOk()->assertJsonPath('amount', '200.0000');
        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'amount' => 200]);
    }

    public function test_user_can_delete_a_budget(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->deleteJson(route('api.v1.budgets.destroy', ['budget' => $budget->id]))->assertOk();

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_user_cannot_manage_another_users_budget(): void
    {
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $budget = Budget::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $ownerCategory->id,
        ]);

        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();
        Sanctum::actingAs($otherUser, ['*']);

        $this->getJson(route('api.v1.budgets.show', ['budget' => $budget->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $this->patchJson(route('api.v1.budgets.update', ['budget' => $budget->id]), [
            'category_id' => $otherCategory->id,
            'transaction_type' => 'withdrawal',
            'amount' => 50,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->toDateString(),
        ])->assertStatus(Response::HTTP_FORBIDDEN);

        $this->deleteJson(route('api.v1.budgets.destroy', ['budget' => $budget->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * The 'replace' action (mirroring TransactionApiController's schedule replace flow) must not
     * rewrite the source budget's own history: it closes the source row's end_date and creates a
     * brand new row for the new pattern/amount, rather than mutating the source in place.
     */
    public function test_replace_action_closes_source_budget_and_creates_a_new_one(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        $sourceStartDate = Carbon::now()->subMonths(2)->startOfDay();
        $source = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $sourceStartDate,
            'end_date' => null,
            'count' => null,
        ]);

        $newStartDate = Carbon::now()->startOfDay();
        $originalEndDate = $newStartDate->copy()->subDay();

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'action' => 'replace',
            'id' => $source->id,
            'category_id' => $category->id,
            'transaction_type' => $source->transaction_type->value,
            'amount' => 200,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => $newStartDate->toDateString(),
            'original_schedule_config' => [
                'frequency' => 'MONTHLY',
                'interval' => 1,
                'start_date' => $sourceStartDate->toDateString(),
                'end_date' => $originalEndDate->toDateString(),
            ],
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('amount', '200.0000');
        $this->assertSame($newStartDate->toDateString(), Carbon::parse($response->json('start_date'))->toDateString());

        $newBudgetId = $response->json('id');
        $this->assertNotSame($source->id, $newBudgetId);

        // The source keeps its own amount/pattern - only end_date is closed. end_date is a
        // virtual attribute (decomposed from `rrule`), not a real column, so it's checked via
        // the model rather than assertDatabaseHas().
        $this->assertDatabaseHas('budgets', [
            'id' => $source->id,
            'amount' => 100,
        ]);
        $this->assertSame(
            $originalEndDate->toDateString(),
            Budget::find($source->id)->end_date->toDateString(),
        );
        $this->assertDatabaseHas('budgets', [
            'id' => $newBudgetId,
            'amount' => 200,
            'start_date' => $newStartDate->toDateString(),
        ]);
    }

    public function test_replace_action_requires_the_source_budget_to_be_owned_by_the_user(): void
    {
        $owner = User::factory()->create();
        $ownerCategory = Category::factory()->for($owner)->create();
        $source = Budget::factory()->create([
            'user_id' => $owner->id,
            'category_id' => $ownerCategory->id,
        ]);

        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();
        Sanctum::actingAs($otherUser, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'action' => 'replace',
            'id' => $source->id,
            'category_id' => $otherCategory->id,
            'transaction_type' => 'withdrawal',
            'amount' => 200,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->toDateString(),
            'original_schedule_config' => [
                'frequency' => 'MONTHLY',
                'interval' => 1,
                'start_date' => Carbon::now()->subMonth()->toDateString(),
                'end_date' => Carbon::now()->subDay()->toDateString(),
            ],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['id']);
    }

    public function test_index_returns_only_the_authenticated_users_budgets(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Budget::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);

        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->for($otherUser)->create();
        Budget::factory()->create(['user_id' => $otherUser->id, 'category_id' => $otherCategory->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.budgets.index'));

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_user_can_create_a_budget_with_a_days_before_month_end_pattern(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => 3,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('days_before_month_end', 3)
            ->assertJsonPath('by_day', null)
            ->assertJsonPath('last_business_day_of_month', false);
    }

    public function test_user_can_create_a_budget_with_a_last_business_day_of_month_pattern(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'last_business_day_of_month' => true,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('last_business_day_of_month', true)
            ->assertJsonPath('by_day', null)
            ->assertJsonPath('days_before_month_end', null);
    }

    public function test_days_before_month_end_is_mutually_exclusive_with_by_day(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'by_day' => '1WE',
            'days_before_month_end' => 3,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['days_before_month_end']);
    }

    public function test_last_business_day_of_month_is_mutually_exclusive_with_by_day(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'by_day' => '1WE',
            'last_business_day_of_month' => true,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['last_business_day_of_month']);
    }

    public function test_days_before_month_end_requires_a_monthly_or_yearly_frequency(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => 3,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['days_before_month_end']);
    }

    public function test_yearly_days_before_month_end_requires_by_month(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'YEARLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => 3,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['by_month']);
    }

    public function test_yearly_days_before_month_end_with_by_month_is_accepted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'YEARLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => 3,
            'by_month' => 6,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('days_before_month_end', 3)
            ->assertJsonPath('by_month', 6);
    }

    public function test_days_before_month_end_must_be_between_0_and_27(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => 28,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['days_before_month_end']);
    }

    public function test_last_business_day_of_month_requires_a_monthly_or_yearly_frequency(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'last_business_day_of_month' => true,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['last_business_day_of_month']);
    }

    public function test_yearly_last_business_day_of_month_requires_by_month(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'YEARLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'last_business_day_of_month' => true,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['by_month']);
    }

    public function test_last_business_day_of_month_is_mutually_exclusive_with_days_before_month_end(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => 3,
            'last_business_day_of_month' => true,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['days_before_month_end', 'last_business_day_of_month']);
    }

    public function test_days_before_month_end_rejects_a_negative_value(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => -1,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['days_before_month_end']);
    }

    #[DataProvider('daysBeforeMonthEndBoundaryProvider')]
    public function test_days_before_month_end_accepts_the_inclusive_boundaries(int $days): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Currency::factory()->for($user)->create(['base' => true]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson(route('api.v1.budgets.store'), [
            'category_id' => $category->id,
            'transaction_type' => 'withdrawal',
            'amount' => 100,
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'start_date' => Carbon::now()->subDay()->toDateString(),
            'days_before_month_end' => $days,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('days_before_month_end', $days);
    }

    public static function daysBeforeMonthEndBoundaryProvider(): array
    {
        return [
            'last day of the month' => [0],
            'upper bound' => [27],
        ];
    }
}
