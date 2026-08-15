<?php

namespace Tests\Feature\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiAbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One representative route per controller per required ability - not exhaustive over
     * every route, but enough to prove each controller's middleware() is actually wired up.
     * [route name, HTTP method, required ability, route params]
     *
     * Route params that reference a specific owned record are resolved per-test via
     * resolveParams() rather than a hardcoded id, since RefreshDatabase does not reset
     * MySQL's auto-increment counter between tests.
     */
    public static function routeAbilityProvider(): array
    {
        return [
            // read
            'accounts.index' => ['api.v1.accounts.index', 'get', 'read', []],
            'categories.index' => ['api.v1.categories.index', 'get', 'read', []],
            'transactions.index' => ['api.v1.transactions.index', 'get', 'read', []],
            'payees.index' => ['api.v1.payees.index', 'get', 'read', []],
            'payees.category-stats' => ['api.v1.payees.category-stats', 'get', 'read', ['accountEntity' => 1]],
            'tags.index' => ['api.v1.tags.index', 'get', 'read', []],
            'tags.show' => ['api.v1.tags.show', 'get', 'read', ['tag' => 1]],
            'investments.index' => ['api.v1.investments.index', 'get', 'read', []],
            'investments.display-data' => ['api.v1.investments.display-data', 'get', 'read', ['investment' => 1]],
            'reports.cashflow' => ['api.v1.reports.cashflow', 'get', 'read', []],
            'imports.file-profiles.index' => ['api.v1.imports.file-profiles.index', 'get', 'read', []],
            'documents.index' => ['api.v1.documents.index', 'get', 'read', []],
            'category-learning.index' => ['api.v1.category-learning.index', 'get', 'read', []],
            'currency-rates.index' => ['api.v1.currency-rates.index', 'get', 'read', ['from' => 1, 'to' => 2]],
            'investment-prices.index' => ['api.v1.investment-prices.index', 'get', 'read', ['investment' => 1]],
            'investment-price-providers.available' => ['api.v1.investment-price-providers.available', 'get', 'read', []],
            'onboarding.show' => ['api.v1.onboarding.show', 'get', 'read', ['topic' => 'dashboard']],
            'budgets.index' => ['api.v1.budgets.index', 'get', 'read', []],
            'reports.budget-chart' => ['api.v1.reports.budget-chart', 'get', 'read', []],
            'transactions.scheduled-items' => ['api.v1.transactions.scheduled-items', 'get', 'read', []],

            // write
            'transactions.store-standard' => ['api.v1.transactions.store-standard', 'post', 'write', []],
            'budgets.store' => ['api.v1.budgets.store', 'post', 'write', []],
            'categories.store' => ['api.v1.categories.store', 'post', 'write', []],
            'payees.store' => ['api.v1.payees.store', 'post', 'write', []],
            'tags.patch-active' => ['api.v1.tags.patch-active', 'patch', 'write', ['tag' => 1]],
            'investments.destroy' => ['api.v1.investments.destroy', 'delete', 'write', ['investment' => 1]],
            'account-entities.destroy' => ['api.v1.account-entities.destroy', 'delete', 'write', ['accountEntity' => 1]],
            'account-groups.destroy' => ['api.v1.account-groups.destroy', 'delete', 'write', ['accountGroup' => 1]],
            'investment-groups.destroy' => ['api.v1.investment-groups.destroy', 'delete', 'write', ['investmentGroup' => 1]],
            'imports.parse' => ['api.v1.imports.parse', 'post', 'write', []],
            'imports.file-profiles.store' => ['api.v1.imports.file-profiles.store', 'post', 'write', []],
            'documents.store' => ['api.v1.documents.store', 'post', 'write', []],
            'category-learning.store' => ['api.v1.category-learning.store', 'post', 'write', []],
            'currency-rates.store' => ['api.v1.currency-rates.store', 'post', 'write', []],
            'investment-prices.store' => ['api.v1.investment-prices.store', 'post', 'write', []],
            'investment-price-providers.test-fetch' => ['api.v1.investment-price-providers.test-fetch', 'post', 'write', ['providerKey' => 'alpha_vantage']],
            'onboarding.dismiss' => ['api.v1.onboarding.dismiss', 'post', 'write', ['topic' => 'dashboard']],
            'maintenance.recalculate-account-monthly-summaries' => ['api.v1.maintenance.recalculate-account-monthly-summaries', 'post', 'write', []],

            // settings (config/credential controllers)
            'ai.config.show' => ['api.v1.ai.config.show', 'get', 'settings', []],
            'ai.config.store' => ['api.v1.ai.config.store', 'post', 'settings', []],
            'ai.settings.show' => ['api.v1.ai.settings.show', 'get', 'settings', []],
            'ai.settings.update' => ['api.v1.ai.settings.update', 'patch', 'settings', []],
            'google-drive.config.show' => ['api.v1.google-drive.config.show', 'get', 'settings', []],
            'google-drive.config.store' => ['api.v1.google-drive.config.store', 'post', 'settings', []],
            'investment-provider-configs.index' => ['api.v1.investment-provider-configs.index', 'get', 'settings', []],
            'investment-provider-configs.update' => ['api.v1.investment-provider-configs.update', 'patch', 'settings', ['providerKey' => 'alpha_vantage']],
            'users.me.settings' => ['api.v1.users.me.settings', 'patch', 'settings', []],
            'users.me.password' => ['api.v1.users.me.password', 'patch', 'settings', []],

            // settings (maintenance-route overrides - NOT their controller's domain default)
            'maintenance.clear-currency-cache' => ['api.v1.maintenance.clear-currency-cache', 'post', 'settings', []],
            'maintenance.cleanup-ai-document-old-files' => ['api.v1.maintenance.cleanup-ai-document-old-files', 'post', 'settings', []],
        ];
    }

    #[DataProvider('routeAbilityProvider')]
    public function test_bearer_token_without_required_ability_is_denied(
        string $routeName,
        string $method,
        string $requiredAbility,
        array $params
    ): void {
        $user = User::factory()->create();
        $params = $this->resolveParams($routeName, $params, $user);

        $otherAbilities = array_values(array_diff(['read', 'write', 'settings'], [$requiredAbility]));
        Sanctum::actingAs($user, $otherAbilities === [] ? ['read'] : $otherAbilities);

        $response = $this->json($method, route($routeName, $params));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    #[DataProvider('routeAbilityProvider')]
    public function test_bearer_token_with_required_ability_is_not_denied_by_ability_check(
        string $routeName,
        string $method,
        string $requiredAbility,
        array $params
    ): void {
        $user = User::factory()->create();
        $params = $this->resolveParams($routeName, $params, $user);

        Sanctum::actingAs($user, [$requiredAbility]);

        $response = $this->json($method, route($routeName, $params));

        // Not asserting 200/201 - the route may still 404/422 on missing fixture data or
        // validation. The point is proving the ABILITY check itself does not block it -
        // a 403 here means the middleware wiring is missing or wrong for this route.
        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * Routes with a model-bound route parameter need a real, user-owned row - RefreshDatabase
     * rolls back data between tests but MySQL does not roll back auto-increment counters, so a
     * hardcoded id would only work for the first test in the run.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function resolveParams(string $routeName, array $params, User $user): array
    {
        return match ($routeName) {
            'api.v1.currency-rates.index' => [
                'from' => Currency::factory()->for($user)->create(['name' => 'US Dollar', 'iso_code' => 'USD'])->id,
                'to' => Currency::factory()->for($user)->create(['name' => 'Euro', 'iso_code' => 'EUR'])->id,
            ],
            'api.v1.investment-prices.index' => [
                'investment' => $this->createOwnedInvestment($user)->id,
            ],
            'api.v1.investments.display-data' => [
                'investment' => $this->createOwnedInvestment($user)->id,
            ],
            'api.v1.tags.show', 'api.v1.tags.patch-active' => [
                'tag' => Tag::factory()->for($user)->create()->id,
            ],
            'api.v1.investments.destroy' => [
                'investment' => $this->createOwnedInvestment($user)->id,
            ],
            'api.v1.account-entities.destroy' => [
                'accountEntity' => $this->createOwnedAccountEntity($user)->id,
            ],
            'api.v1.payees.category-stats' => [
                'accountEntity' => $this->createOwnedPayee($user)->id,
            ],
            'api.v1.account-groups.destroy' => [
                'accountGroup' => AccountGroup::factory()->for($user)->create()->id,
            ],
            'api.v1.investment-groups.destroy' => [
                'investmentGroup' => InvestmentGroup::factory()->for($user)->create()->id,
            ],
            default => $params,
        };
    }

    private function createOwnedAccountEntity(User $user): AccountEntity
    {
        return AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);
    }

    private function createOwnedPayee(User $user): AccountEntity
    {
        return AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
            ]);
    }

    private function createOwnedInvestment(User $user): Investment
    {
        $currency = $user->currencies()->first() ?? Currency::factory()->for($user)->create();
        $investmentGroup = $user->investmentGroups()->first() ?? InvestmentGroup::factory()->for($user)->create();

        return Investment::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);
    }
}
