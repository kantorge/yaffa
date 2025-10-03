<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('currency');
        $this->setBaseModel(Currency::class);
    }

    /** @test */
    public function guest_cannot_access_resource(): void
    {
        $this->get(route("{$this->base_route}.index"))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create"))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store"))->assertRedirect(route('login'));

        /** @var User $user */
        $user = User::factory()->create();
        /** @var Currency $currency */
        $currency = Currency::factory()->for($user)->create();

        $this->get(route("{$this->base_route}.edit", $currency))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", $currency))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", $currency))->assertRedirect(route('login'));
    }

    /** @test */
    public function user_cannot_access_other_users_resource(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        $currency = $this->createForUser($user1, $this->base_model);

        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->actingAs($user2)->get(route("{$this->base_route}.edit", $currency))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", $currency))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", $currency))->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function user_can_view_list_of_currencies(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.index");
    }

    /** @test */
    public function user_can_access_create_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function user_cannot_create_a_currency_with_missing_data(): void
    {
        /** @var User $user */
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
    public function user_can_create_a_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertCreateForUser($user, [
            'base' => true, // The first currency is expected to be the base currency
        ]);
    }

    /** @test */
    public function user_can_edit_an_existing_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $currency = $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.edit", $currency));

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function user_cannot_update_a_currency_with_missing_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $currency = $this->createForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $currency),
                [
                    'id' => $currency->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_update_a_currency_with_proper_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $currency = $this->createForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $currency),
                [
                    'id' => $currency->id,
                    'name' => $currency->name . '_2',
                    'iso_code' => $currency->iso_code,
                    'base' => $currency->base,
                    'auto_update' => $currency->auto_update,
                ]
            );

        $response->assertRedirect($this->base_route);
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    /** @test */
    public function user_can_delete_an_existing_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
    }
}
