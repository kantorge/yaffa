<?php

namespace Tests\Feature;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The account show page resolves its initial date-range preset with the precedence
 * URL param > account setting > user setting > default. That resolution happens
 * entirely server-side in AccountEntityController::show() and is handed to the page
 * as a `window.filters` JS variable (via JavaScriptFacade) for the Vue date filter
 * component to render - no client-side logic is involved, so this is covered here
 * instead of via Dusk.
 */
class AccountShowDatePresetTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountAndUser(): AccountEntity
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $account */
        return AccountEntity::factory()->asAccount($user)->create();
    }

    /**
     * @return array{date_from?: string, date_to?: string, date_preset?: string}
     */
    private function resolvedFilters(TestResponse $response): array
    {
        preg_match('/window\.filters\s*=\s*(\{.*?\});/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'window.filters script variable not found in response.');

        return json_decode($matches[1], true);
    }

    public function test_date_parameters_take_precedence_over_preset_settings(): void
    {
        $account = $this->createAccountAndUser();
        $account->config->default_date_range = 'previous90Days';
        $account->config->save();
        $account->user->account_details_date_range = 'previous30Days';
        $account->user->save();

        $response = $this->actingAs($account->user)->get(route('account-entity.show', [
            'account_entity' => $account->id,
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ]));

        $response->assertOk();
        $filters = $this->resolvedFilters($response);
        $this->assertSame('2025-01-01', $filters['date_from']);
        $this->assertSame('2025-01-31', $filters['date_to']);
        $this->assertArrayNotHasKey('date_preset', $filters);
    }

    public function test_preset_parameter_takes_precedence_over_account_and_user_setting(): void
    {
        $account = $this->createAccountAndUser();
        $account->config->default_date_range = 'previous90Days';
        $account->config->save();
        $account->user->account_details_date_range = 'previous30Days';
        $account->user->save();

        $response = $this->actingAs($account->user)->get(route('account-entity.show', [
            'account_entity' => $account->id,
            'date_preset' => 'previous7Days',
        ]));

        $response->assertOk();
        $filters = $this->resolvedFilters($response);
        $this->assertSame('previous7Days', $filters['date_preset']);
    }

    public function test_account_date_preset_setting_takes_precedence_over_user_setting(): void
    {
        $account = $this->createAccountAndUser();
        $account->config->default_date_range = 'previous90Days';
        $account->config->save();
        $account->user->account_details_date_range = 'previous30Days';
        $account->user->save();

        $response = $this->actingAs($account->user)->get(route('account-entity.show', [
            'account_entity' => $account->id,
        ]));

        $response->assertOk();
        $filters = $this->resolvedFilters($response);
        $this->assertSame('previous90Days', $filters['date_preset']);
    }

    public function test_user_date_setting_is_used_if_no_other_date_setting_exists(): void
    {
        $account = $this->createAccountAndUser();
        $account->config->default_date_range = null;
        $account->config->save();
        $account->user->account_details_date_range = 'previous30Days';
        $account->user->save();

        $response = $this->actingAs($account->user)->get(route('account-entity.show', [
            'account_entity' => $account->id,
        ]));

        $response->assertOk();
        $filters = $this->resolvedFilters($response);
        $this->assertSame('previous30Days', $filters['date_preset']);
    }

    public function test_default_date_range_is_used_if_no_settings_exists(): void
    {
        $account = $this->createAccountAndUser();
        $account->config->default_date_range = null;
        $account->config->save();
        $account->user->account_details_date_range = 'none';
        $account->user->save();

        $response = $this->actingAs($account->user)->get(route('account-entity.show', [
            'account_entity' => $account->id,
        ]));

        $response->assertOk();
        $filters = $this->resolvedFilters($response);
        $this->assertSame('none', $filters['date_preset']);
    }
}
