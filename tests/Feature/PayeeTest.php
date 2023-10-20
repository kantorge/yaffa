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

    private function createPayeeAndUser(): AccountEntity
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $account */
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        return $account;
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-entity');
        $this->setBaseModel(AccountEntity::class);
    }

    /** @test */
    public function guest_cannot_access_resource()
    {
        $this->get(route("{$this->base_route}.index", ['type' => 'payee']))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create", ['type' => 'payee']))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store", ['type' => 'payee']))->assertRedirect(route('login'));

        $payee = $this->createPayeeAndUser();

        $this->get(route("{$this->base_route}.edit", ['type' => 'payee', 'account_entity' => $payee->id]))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", ['type' => 'payee', 'account_entity' => $payee->id]))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", ['type' => 'payee', 'account_entity' => $payee->id]))->assertRedirect(route('login'));
    }

    /** @test */
    public function user_cannot_access_other_users_resource()
    {
        $payee = $this->createPayeeAndUser();

        /** @var User $user2 */
        $user2 = User::factory()->create();

        $this->actingAs($user2)->get(route("{$this->base_route}.edit", ['type' => 'payee', 'account_entity' => $payee->id]))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", ['type' => 'payee', 'account_entity' => $payee->id]))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", ['type' => 'payee', 'account_entity' => $payee->id]))->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function user_can_view_list_of_payees()
    {
        /** @var User $user */
        $user = User::factory()->create();

        AccountEntity::factory()->payee()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index", ['type' => 'payee']));

        $response->assertStatus(200);
        $response->assertViewIs('payee.index');
    }

    /** @test */
    public function user_can_access_create_form()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create", ['type' => 'payee']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('payee.form');
    }

    /** @test */
    public function user_cannot_create_a_payee_with_missing_data()
    {
        $user = User::factory()->create();
        $category = $this->createForUser($user, Category::class);

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

    /** @test */
    public function user_can_create_a_payee()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create a class for this user, which can be used in the config
        $this->createForUser($user, Category::class);

        $attributes = $baseAttributes = AccountEntity::factory()->for($user)->raw();
        $attributes['config_type'] = 'payee';
        $attributes['config'] = Payee::factory()->withUser($user)->raw();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'payee']),
                $attributes
            );

        $response->assertRedirect(route("{$this->base_route}.index", ['type' => 'payee']));

        $model = new $this->base_model();

        $this->assertDatabaseHas($model->getTable(), $baseAttributes);
    }

    /** @test */
    public function user_can_edit_an_existing_payee()
    {
        $payee = $this->createPayeeAndUser();
        $user = $payee->user;

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    "{$this->base_route}.edit",
                    ['type' => 'payee', 'account_entity' => $payee->id]
                )
            );

        $response->assertStatus(200);
        $response->assertViewIs('payee.form');
    }

    /** @test */
    public function user_cannot_update_a_payee_with_missing_data()
    {
        $payee = $this->createPayeeAndUser();
        $user = $payee->user;

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    ['type' => 'payee', 'account_entity' => $payee->id]
                ),
                [
                    'id' => $payee->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_update_a_payee_with_proper_data()
    {
        $payee = $this->createPayeeAndUser();
        $user = $payee->user;

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    ['type' => 'payee', 'account_entity' => $payee->id]
                ),
                [
                    'name' => 'Updated payee name',
                    'active' => $payee->active,
                    'config_type' => 'payee',
                    'config' => [
                        'category_id' => $payee->config->category_id,
                    ],
                ]
            );

        $response->assertRedirect(route("{$this->base_route}.index", ['type' => 'payee']));        //TODO: make this dynamic instead of fixed 1st element
        $response->assertSessionHas('notification_collection.0.type', 'success');
    }

    /** @test */
    public function user_can_delete_an_existing_payee()
    {
        $payee = $this->createPayeeAndUser();
        $user = $payee->user;
        $payee->load('config');

        $this->actingAs($user)->deleteJson(route("{$this->base_route}.destroy", $payee->id));

        // Check if model was deleted
        $this->assertDatabaseMissing($payee->getTable(), $payee->attributesToArray());

        // Check if config was also deleted
        $this->assertDatabaseMissing('payees', $payee->config->toArray());
    }
}
