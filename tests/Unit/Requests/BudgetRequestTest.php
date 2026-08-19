<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\BudgetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Exists;
use Tests\TestCase;

/**
 * BudgetPolicy::create() always returns true - the actual guard against attaching another
 * user's category_id/account_id to a budget lives only in BudgetRequest's validation rules,
 * with no Policy-layer backstop (see .ai/docs/features/budget-schedule-redesign/permissions.md).
 * These tests pin the *mechanism*, not just the externally-observable behavior already covered
 * by BudgetApiTest::test_user_cannot_use_another_users_category() and
 * ::test_user_cannot_use_a_payee_as_the_account() - so a refactor that swaps the scoped
 * Rule::exists() for a plain, unscoped 'exists:...' string fails here even though those two
 * black-box tests would keep passing right up until real cross-user data leaked.
 */
class BudgetRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Exists, 1: \Illuminate\Support\Collection<int, array>} the rule and the
     *         where clauses it produces once applied to a real query builder for $table
     */
    private function scopedRuleWheres(string $field, string $table, User $user): array
    {
        $request = BudgetRequest::create('/', 'POST');
        $request->setUserResolver(fn () => $user);

        $rule = collect($request->rules()[$field])->first(fn ($rule) => $rule instanceof Exists);

        $this->assertInstanceOf(
            Exists::class,
            $rule,
            "Expected a scoped Rule::exists() on '{$field}', not a plain string rule."
        );

        $query = DB::table($table);
        foreach ($rule->queryCallbacks() as $callback) {
            $callback($query);
        }

        return [$rule, collect($query->wheres)];
    }

    public function test_category_id_ownership_rule_is_query_scoped_by_user_id(): void
    {
        $user = User::factory()->create();

        [, $wheres] = $this->scopedRuleWheres('category_id', 'categories', $user);

        $this->assertTrue(
            $wheres->contains(fn ($where) => ($where['column'] ?? null) === 'user_id' && ($where['value'] ?? null) === $user->id),
            "Expected the category_id ownership rule to filter 'categories' by the authenticated user's id."
        );
    }

    public function test_account_id_ownership_rule_is_query_scoped_by_user_id_and_rejects_payees(): void
    {
        $user = User::factory()->create();

        [, $wheres] = $this->scopedRuleWheres('account_id', 'account_entities', $user);

        $this->assertTrue(
            $wheres->contains(fn ($where) => ($where['column'] ?? null) === 'user_id' && ($where['value'] ?? null) === $user->id),
            "Expected the account_id ownership rule to filter 'account_entities' by the authenticated user's id."
        );
        $this->assertTrue(
            $wheres->contains(fn ($where) => ($where['column'] ?? null) === 'config_type' && ($where['value'] ?? null) === 'account'),
            'Expected the account_id ownership rule to reject payee ids (config_type must be account).'
        );
    }
}
