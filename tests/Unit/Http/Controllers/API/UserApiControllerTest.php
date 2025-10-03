<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function updates_user_settings_and_returns_updated_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'language' => 'en',
            'locale' => 'en-US',
            'start_date' => '2020-01-01',
            'end_date' => '2020-12-31',
            'account_details_date_range' => 'none',
        ]);
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/settings', [
            'language' => 'hu',
            'locale' => 'hu-HU',
            'start_date' => '2021-01-01',
            'end_date' => '2021-12-31',
            'account_details_date_range' => 'yesterday',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'warnings' => [],
                'data' => [
                    'language' => 'hu',
                    'locale' => 'hu-HU',
                    'start_date' => '2021-01-01T00:00:00.000000Z',
                    'end_date' => '2021-12-31T00:00:00.000000Z',
                    'account_details_date_range' => 'yesterday',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'language' => 'hu',
            'locale' => 'hu-HU',
            'start_date' => '2021-01-01',
            'end_date' => '2021-12-31',
            'account_details_date_range' => 'yesterday',
        ]);
    }

    /** @test */
    public function warns_about_recalculating_monthly_summaries_when_end_date_changes(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['end_date' => '2020-12-31']);
        $user->refresh(); // Refresh the model to get the start_date attribute
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/settings', [
            'language' => $user->language,
            'locale' => $user->locale,
            'start_date' => $user->start_date->format('Y-m-d'),
            'end_date' => $user->start_date->addYear()->format('Y-m-d'),
            'account_details_date_range' => $user->account_details_date_range,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'warnings' => [__('Cached monthly summaries need to be recalculated. This may take a while. Please be patient.')],
            ]);
    }

    /** @test */
    public function warns_about_refreshing_page_when_language_changes(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['language' => 'en']);
        $user->refresh(); // Refresh the model to get the locale attribute
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/settings', [
            'language' => 'hu',
            'locale' => $user->locale,
            'start_date' => $user->start_date->format('Y-m-d'),
            'end_date' => $user->end_date->format('Y-m-d'),
            'account_details_date_range' => $user->account_details_date_range,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'warnings' => [__('The language has been updated. Please refresh the page to see the changes.')],
            ]);
    }

    /** @test */
    public function does_not_warn_when_no_significant_changes_are_made(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['language' => 'en', 'locale' => 'en-US']);
        $user->refresh(); // Refresh the model to get the start_date and end_date attributes
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/settings', [
            'language' => 'en', // same as current
            'locale' => 'en-US', // same as current
            'start_date' => $user->start_date->format('Y-m-d'), // same as current
            'end_date' => $user->end_date->format('Y-m-d'), // same as current
            'account_details_date_range' => $user->account_details_date_range, // same as current
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'warnings' => [],
            ]);
    }

    /** @test */
    public function changing_password_successfully_returns_successful_response(): void
    {
        // Make sure that the sandbox mode is disabled
        config(['yaffa.sandbox_mode' => false]);

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/change_password', [
            'current_password' => 'password', // Assuming the factory sets this as default
            'password' => 'newPassword123',
            'password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function changing_password_with_incorrect_current_password_returns_error(): void
    {
        // Make sure that the sandbox mode is disabled
        config(['yaffa.sandbox_mode' => false]);

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/change_password', [
            'current_password' => 'wrongPassword',
            'password' => 'newPassword123',
            'password_confirmation' => 'newPassword123',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function changing_password_without_password_confirmation_returns_error(): void
    {
        // Make sure that the sandbox mode is disabled
        config(['yaffa.sandbox_mode' => false]);

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/change_password', [
            'current_password' => 'password',
            'password' => 'newPassword123',
            // No password_confirmation provided
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function changing_password_with_short_password_returns_error(): void
    {
        // Make sure that the sandbox mode is disabled
        config(['yaffa.sandbox_mode' => false]);

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/change_password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function password_change_in_sandbox_mode_is_not_allowed(): void
    {
        // Make sure that the sandbox mode is enabled
        config(['yaffa.sandbox_mode' => true]);

        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->json('PATCH', '/api/user/change_password', [
            'current_password' => 'password',
            'password' => 'newPassword',
            'password_confirmation' => 'newPassword',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => __('This action is not allowed in sandbox mode.'),
        ]);
    }
}
