<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\Feature\Concerns\AuthorizesResourceCrud;
use Tests\Feature\Concerns\AuthorizesResourceCrudForUnverifiedUsers;
use Tests\TestCase;

class AccountGroupTest extends TestCase
{
    use AuthorizesResourceCrud;
    use AuthorizesResourceCrudForUnverifiedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-groups');
        $this->setBaseModel(AccountGroup::class);
    }

    public function test_user_can_view_list_of_account_groups(): void
    {
        $user = User::factory()->create();

        $this->createForUser($user, $this->base_model, [], 5);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs('account-groups.index');
    }

    public function test_user_can_access_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('account-groups.form');
    }

    public function test_user_cannot_create_an_account_group_with_missing_data(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store"),
                [
                    'name' => '',
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_create_an_account_group(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertCreateForUser($user);
    }

    public function test_user_can_edit_an_existing_account_group(): void
    {
        $user = User::factory()->create();

        $accountGroup = $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.edit", $accountGroup));

        $response->assertStatus(200);
        $response->assertViewIs('account-groups.form');
    }

    public function test_user_cannot_update_an_account_group_with_missing_data(): void
    {
        $user = User::factory()->create();

        $accountGroup = $this->createForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $accountGroup),
                [
                    'id' => $accountGroup->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_an_account_group_with_proper_data(): void
    {
        $user = User::factory()->create();

        $accountGroup = $this->createForUser($user, $this->base_model);
        $accountGroup2 = $this->rawForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $accountGroup),
                [
                    'id' => $accountGroup->id,
                    'name' => $accountGroup2['name'],
                ]
            );

        $response->assertRedirect($this->base_route);
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    public function test_user_can_delete_an_existing_account_group(): void
    {
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
    }

    public function test_user_cannot_delete_account_group_with_attached_account(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $accountGroup */
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();
        $account->load('config');
        $accountGroup = $account->config->accountGroup;

        $response = $this->actingAs($user)->deleteJson(route("{$this->base_route}.destroy", $accountGroup->id));
        $response->assertSessionHas('notification_collection.0.type', 'danger');

        $this->assertDatabaseHas($accountGroup->getTable(), $accountGroup->toArray());
    }
}
