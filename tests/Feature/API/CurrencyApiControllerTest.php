<?php

namespace Tests\Feature\API;

use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class CurrencyApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_a_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $currency */
        $currency = Currency::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.v1.currencies.destroy', ['currency' => $currency->id]));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }

    public function test_it_does_not_delete_another_users_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        /** @var Currency $currency */
        $currency = Currency::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.v1.currencies.destroy', ['currency' => $currency->id]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }

    public function test_it_does_not_delete_the_base_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $currency */
        $currency = Currency::factory()->for($user)->create(['base' => true]);

        $response = $this->actingAs($user)
            ->deleteJson(route('api.v1.currencies.destroy', ['currency' => $currency->id]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson(['error' => __('Base currency cannot be deleted')]);
        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }

    public function test_it_does_not_delete_a_currency_in_use(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $currency */
        $currency = Currency::factory()->for($user)->create();

        AccountEntity::factory()->asAccount($user, ['currency_id' => $currency->id])->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.v1.currencies.destroy', ['currency' => $currency->id]));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson(['error' => __('Currency is in use, cannot be deleted')]);
        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }
}
