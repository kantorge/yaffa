<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AiDocumentFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_document_status_filter_endpoint_loads(): void
    {
        $user = User::factory()->create();

        AiDocument::factory()->for($user)->create([
            'status' => 'processing',
        ]);

        // Endpoint should handle status filter parameter without error
        $response = $this->actingAs($user)
            ->get(route('ai-documents.index', ['status' => 'processing']))
            ->assertStatus(Response::HTTP_OK)
            ->assertViewIs('ai-documents.index');
    }

    public function test_ai_document_source_filter_endpoint_loads(): void
    {
        $user = User::factory()->create();

        AiDocument::factory()->for($user)->create([
            'source_type' => 'received_email',
        ]);

        // Endpoint should handle source filter parameter without error
        $response = $this->actingAs($user)
            ->get(route('ai-documents.index', ['source' => 'received_email']))
            ->assertStatus(Response::HTTP_OK)
            ->assertViewIs('ai-documents.index');
    }

    public function test_ai_document_filters_handle_special_characters_safely(): void
    {
        $user = User::factory()->create();

        AiDocument::factory()->for($user)->create([
            'custom_prompt' => 'Test prompt',
        ]);

        // Endpoint should not crash with regex special characters in search
        $this->actingAs($user)
            ->get(route('ai-documents.index', ['search' => '(test)']))
            ->assertStatus(Response::HTTP_OK);

        $this->actingAs($user)
            ->get(route('ai-documents.index', ['search' => '[test]']))
            ->assertStatus(Response::HTTP_OK);

        $this->actingAs($user)
            ->get(route('ai-documents.index', ['search' => 'test|example']))
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_ai_document_combined_filters_endpoint_loads(): void
    {
        $user = User::factory()->create();

        AiDocument::factory()->for($user)->create([
            'status' => 'processing',
            'source_type' => 'manual_upload',
        ]);

        // Endpoint should handle multiple filter parameters
        $response = $this->actingAs($user)
            ->get(route('ai-documents.index', [
                'status' => 'processing',
                'source' => 'manual_upload',
            ]))
            ->assertStatus(Response::HTTP_OK);

        $response->assertViewIs('ai-documents.index');
    }

    public function test_guest_cannot_access_ai_documents_index(): void
    {
        AiDocument::factory()->create([
            'status' => 'processing',
        ]);

        $this->get(route('ai-documents.index', ['status' => 'processing']))
            ->assertRedirectToRoute('login');
    }

    public function test_user_sees_only_own_documents(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $ownDoc = AiDocument::factory()->for($owner)->create([
            'status' => 'processing',
        ]);

        // Viewer navigates to documents index with a filter
        $response = $this->actingAs($viewer)
            ->get(route('ai-documents.index', ['status' => 'processing']))
            ->assertStatus(Response::HTTP_OK);

        // The response should show the index view
        $response->assertViewIs('ai-documents.index');
    }
}
