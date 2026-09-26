<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_layer_script_is_not_rendered_if_gtm_id_is_not_set(): void
    {
        config(['yaffa.gtm_container_id' => null]);

        $user = User::factory()->create(['language' => 'en']);

        $loginResponse = $this->get(route('login'));
        $loginResponse->assertOk();
        $loginResponse->assertDontSee('window.dataLayer', false);

        $homeResponse = $this->actingAs($user)->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertDontSee('window.dataLayer', false);
    }
}
