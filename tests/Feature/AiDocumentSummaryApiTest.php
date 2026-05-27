<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDocumentSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_hidden_when_no_provider_config(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.summary'));

        $response->assertOk()
            ->assertJson(['active_provider' => false]);
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson(route('api.v1.documents.summary'))
            ->assertUnauthorized();
    }

    public function test_summary_returns_correct_counts(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        AiDocument::factory()->for($user)->create(['status' => 'ready_for_review']);
        AiDocument::factory()->for($user)->create(['status' => 'ready_for_review']);
        AiDocument::factory()->for($user)->create(['status' => 'processing_failed']);
        AiDocument::factory()->for($user)->create(['status' => 'finalized']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.summary'));

        $response->assertOk()
            ->assertJson([
                'active_provider' => true,
                'total' => 3,
                'ready_for_review' => 2,
                'processing_failed' => 1,
            ]);
    }

    public function test_summary_excludes_other_users_documents(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        AiDocument::factory()->for($user)->create(['status' => 'ready_for_review']);
        AiDocument::factory()->for($otherUser)->create(['status' => 'ready_for_review']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.summary'));

        $response->assertOk()
            ->assertJson([
                'active_provider' => true,
                'total' => 1,
                'ready_for_review' => 1,
            ]);
    }

    public function test_summary_oldest_created_at_is_null_when_no_documents(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.summary'));

        $response->assertOk()
            ->assertJson([
                'active_provider' => true,
                'total' => 0,
                'oldest_created_at' => null,
            ]);
    }

    public function test_summary_oldest_created_at_is_non_finalized_document(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        $older = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'created_at' => now()->subDays(10),
        ]);
        AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'created_at' => now()->subDays(2),
        ]);
        // Finalized document is older but should be excluded
        AiDocument::factory()->for($user)->create([
            'status' => 'finalized',
            'created_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.summary'));

        $response->assertOk();
        $this->assertStringStartsWith(
            $older->created_at->toDateString(),
            $response->json('oldest_created_at'),
        );
    }
}
