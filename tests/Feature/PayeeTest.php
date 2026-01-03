<?php

namespace Tests\Feature;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class PayeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-entity');
        $this->setBaseModel(AccountEntity::class);
    }

    public function test_guest_cannot_access_resource(): void
    {
        $this->get(route("{$this->base_route}.index", ['type' => 'payee']))->assertRedirectToRoute('login');
        $this->get(route("{$this->base_route}.create", ['type' => 'payee']))->assertRedirectToRoute('login');
        $this->post(route("{$this->base_route}.store", ['type' => 'payee']))->assertRedirectToRoute('login');

        /** @var User $user */
        $user = User::factory()->create();
        Category::factory()->for($user)->create();
        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')->create();

        $this->get(route("{$this->base_route}.edit", ['account_entity' => $payee->id]))
            ->assertRedirectToRoute('login');
        $this->patch(route("{$this->base_route}.update", ['account_entity' => $payee->id]))
            ->assertRedirectToRoute('login');
    }

    public function test_user_cannot_access_other_users_resource(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->for($user1)->for(Payee::factory()->withUser($user1), 'config')->create();

        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->actingAs($user2)->get(route("{$this->base_route}.edit", ['account_entity' => $payee->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", ['account_entity' => $payee->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_can_view_list_of_payees(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->count(5)
            ->create();

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index", ['type' => 'payee']));

        $response->assertStatus(200);
        $response->assertViewIs('payee.index');
    }

    public function test_user_can_access_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create", ['type' => 'payee']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('payee.form');
    }

    public function test_user_cannot_create_a_payee_with_missing_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()->for($user)->create();
        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'payee']),
                [
                    'name' => '',
                    'active' => 1,
                    'config_type' => 'payee',
                    'config' => [
                        'category_id' => $category->id,
                    ],
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_create_a_payee(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $attributes = $baseAttributes = AccountEntity::factory()->for($user)->raw();
        $attributes['config_type'] = 'payee';
        $attributes['config'] = Payee::factory()->withUser($user)->raw();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'payee']),
                $attributes
            );

        $response->assertRedirectToRoute("{$this->base_route}.index", ['type' => 'payee']);

        $model = new $this->base_model();

        $this->assertDatabaseHas($model->getTable(), $baseAttributes);
    }

    public function test_user_can_edit_an_existing_payee(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')->create();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    "{$this->base_route}.edit",
                    ['account_entity' => $payee->id]
                )
            );

        $response->assertStatus(200);
        $response->assertViewIs('payee.form');
    }

    public function test_user_cannot_update_a_payee_with_missing_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')->create();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    ['account_entity' => $payee->id]
                ),
                [
                    'id' => $payee->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_a_payee_with_proper_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->for($user)->for(Payee::factory()->withUser($user), 'config')->create();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    ['account_entity' => $payee->id]
                ),
                [
                    'name' => 'Another payee name',
                    'active' => $payee->active,
                    'config_type' => 'payee',
                    'config' => [
                        'category_id' => $payee->config->category_id,
                    ],
                ]
            );

        $response->assertRedirectToRoute("{$this->base_route}.index", ['type' => 'payee']);
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }
}
