<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class OnboardingApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_onboarding_data_returns_correct_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.v1.onboarding.show', ['topic' => 'dashboard']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['dismissed', 'steps']);
    }

    public function test_set_dismissed_flag_sets_correct_flag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.onboarding.dismiss', ['topic' => 'dashboard']));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertTrue($user->fresh()->hasFlag('dismissOnboardingWidgetDashboard'));
    }

    public function test_set_completed_tour_flag_sets_correct_flag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.onboarding.complete-tour', ['topic' => 'dashboard']));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertTrue($user->fresh()->hasFlag('viewProductTour-Dashboard'));
    }

    public function test_get_onboarding_data_for_unknown_topic_throws_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.v1.onboarding.show', ['topic' => 'missingTopic']));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
