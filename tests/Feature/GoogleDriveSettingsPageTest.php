<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleDriveSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/user/settings');

        $response->assertStatus(200);
        $response->assertViewIs('user.settings');
        $response->assertSee('my-profile');
    }

    public function test_ai_settings_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.ai-settings'));

        $response->assertOk();
        $response->assertViewIs('user.ai-settings');
        $response->assertSee('ai-settings');
    }

    public function test_investment_provider_settings_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.investment-provider-settings'));

        $response->assertOk();
        $response->assertViewIs('user.investment-provider-settings');
        $response->assertSee('investment-provider-settings');
    }

    public function test_guest_cannot_access_settings_pages(): void
    {
        $this->get(route('user.settings'))->assertRedirectToRoute('login');
        $this->get(route('user.ai-settings'))->assertRedirectToRoute('login');
        $this->get(route('user.investment-provider-settings'))->assertRedirectToRoute('login');
    }
}
