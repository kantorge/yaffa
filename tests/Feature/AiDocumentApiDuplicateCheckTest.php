<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDocumentApiDuplicateCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_duplicates_returns_bad_request_for_unprocessed_document(): void
    {
        $user = User::factory()->create();

        $document = AiDocument::factory()->for($user)->create([
            'processed_transaction_data' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/documents/{$document->id}/check-duplicates");

        $response->assertStatus(400);
    }

    public function test_check_duplicates_resolves_service_and_returns_empty_when_no_matches(): void
    {
        $user = User::factory()->create();

        $document = AiDocument::factory()->for($user)->create([
            'processed_transaction_data' => [
                'raw' => [
                    'date' => now()->toDateString(),
                    'amount' => 123.45,
                ],
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/documents/{$document->id}/check-duplicates");

        $response->assertStatus(200)
            ->assertJson([
                'duplicates' => [],
            ]);
    }
}
