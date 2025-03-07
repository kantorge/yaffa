<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AccountGroupTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-group');
        $this->setBaseModel(AccountGroup::class);
    }

    /** @test */
    public function guest_cannot_access_resource()
    {
        $this->get(route("{$this->base_route}.index"))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create"))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store"))->assertRedirect(route('login'));


        $user = User::factory()->create();
        $accountGroup = $this->createForUser($user, $this->base_model);

        $this->get(route("{$this->base_route}.edit", $accountGroup))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", $accountGroup))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", $accountGroup))->assertRedirect(route('login'));
    }

    /** @test */
    public function unverified_user_cannot_access_resource()
    {
        $user_unverified = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.index"))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->get(route("{$this->base_route}.create"))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->post(route("{$this->base_route}.store"))->assertRedirect(route('verification.notice'));

        $user = User::factory()->create();
        $accountGroup = $this->createForUser($user, $this->base_model);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.edit", $accountGroup))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->patch(route("{$this->base_route}.update", $accountGroup))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->delete(route("{$this->base_route}.destroy", $accountGroup))->assertRedirect(route('verification.notice'));
    }

    /** @test */
    public function user_cannot_access_other_users_resource()
    {
        $user1 = User::factory()->create();
        $accountGroup = $this->createForUser($user1, $this->base_model);

        $user2 = User::factory()->create();

        $this->actingAs($user2)->get(route("{$this->base_route}.edit", $accountGroup))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", $accountGroup))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", $accountGroup))->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function user_can_view_list_of_account_groups()
    {
        $user = User::factory()->create();

        $this->createForUser($user, $this->base_model, [], 5);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.index");
    }

    /** @test */
    public function user_can_access_create_form()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function user_cannot_create_an_account_group_with_missing_data()
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

    /** @test */
    public function user_can_create_an_account_group()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertCreateForUser($user);
    }

    /** @test */
    public function user_can_edit_an_existing_account_group()
    {
        $user = User::factory()->create();

        $accountGroup = $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.edit", $accountGroup));

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function user_cannot_update_an_account_group_with_missing_data()
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

    /** @test */
    public function user_can_update_an_account_group_with_proper_data()
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

    /** @test */
    public function user_can_delete_an_existing_account_group()
    {
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
    }

    /** @test */
    public function user_cannot_delete_account_group_with_attached_account()
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
