<?php

namespace Tests\Feature\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountGroupApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_destroysAccountGroupSuccessfully(): void
    {
        $accountGroup = AccountGroup::factory()->for($this->user)->create();

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->deleteJson(route('api.v1.account-groups.destroy', $accountGroup));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('account_groups', ['id' => $accountGroup->id]);
    }

    public function test_doesNotDestroyAccountGroupWithoutAuthorization(): void
    {
        $accountGroup = AccountGroup::factory()->create();

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->deleteJson(route('api.v1.account-groups.destroy', $accountGroup));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('account_groups', ['id' => $accountGroup->id]);
    }

    public function test_doesNotDestroyAccountGroupInUse(): void
    {
        $account = AccountEntity::factory()
            ->for($this->user)
            ->for(Account::factory()->withUser($this->user), 'config')
            ->create();
        $account->load('config');
        $accountGroup = $account->config->accountGroup;

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->deleteJson(route('api.v1.account-groups.destroy', $accountGroup));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson(['error' => __('Account group is in use, cannot be deleted')]);
        $this->assertDatabaseHas('account_groups', ['id' => $accountGroup->id]);
    }
}
