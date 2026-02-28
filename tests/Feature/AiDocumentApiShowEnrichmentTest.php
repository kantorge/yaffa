<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDocumentApiShowEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_enriches_recommended_category_full_name(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'name' => 'Groceries',
            'parent_id' => null,
        ]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => [
                'transaction_type' => 'withdrawal',
                'config_type' => 'standard',
                'config' => [
                    'account_from_id' => null,
                    'account_to_id' => null,
                ],
                'transaction_items' => [
                    [
                        'description' => 'Milk',
                        'amount' => 3.5,
                        'recommended_category_id' => $category->id,
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/documents/{$document->id}");

        $response->assertOk()
            ->assertJsonPath('document.processed_transaction_data.transaction_items.0.recommended_category_full_name', $category->full_name)
            ->assertJsonPath('document.processed_transaction_data.matched_entities', []);
    }
}
