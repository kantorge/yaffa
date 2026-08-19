<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\Feature\Concerns\AuthorizesResourceCrud;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use AuthorizesResourceCrud;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('currencies');
        $this->setBaseModel(Currency::class);
    }

    public function test_user_can_view_list_of_currencies(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs('currencies.index');
    }

    public function test_user_can_access_create_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('currencies.form');
    }

    public function test_user_cannot_create_a_currency_with_missing_data(): void
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

    public function test_user_can_create_a_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertCreateForUser($user, [
            'base' => true, // The first currency is expected to be the base currency
        ]);
    }

    public function test_user_can_edit_an_existing_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $currency = $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.edit", $currency));

        $response->assertStatus(200);
        $response->assertViewIs('currencies.form');
    }

    public function test_user_cannot_update_a_currency_with_missing_data(): void
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

    public function test_user_can_update_a_currency_with_proper_data(): void
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

    public function test_user_can_delete_an_existing_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
    }

    public function test_factory_deduplicates_for_authenticated_user_with_explicit_null_user_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $taken = CurrencyData::getCurrencies()[0];

        Currency::factory()->for($user)->create([
            'name' => $taken['name'],
            'iso_code' => $taken['iso_code'],
        ]);

        // user_id is explicitly null, not omitted - the factory must still resolve
        // the authenticated user as the owner for its collision check, since the
        // model's creating hook will fill user_id from auth() on save anyway.
        $currency = Currency::factory()->make([
            'name' => $taken['name'],
            'iso_code' => $taken['iso_code'],
            'user_id' => null,
        ]);

        $this->assertNotSame($taken['iso_code'], $currency->iso_code);
    }
}
