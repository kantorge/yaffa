<?php

namespace Tests\Feature;

use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDocumentApiIndexCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_frontend_compatible_paginated_payload(): void
    {
        $user = User::factory()->create();

        $document = AiDocument::factory()->for($user)->create();
        AiDocumentFile::factory()->for($document)->create([
            'file_name' => 'receipt.pdf',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.index', ['per_page' => 10]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'source_type',
                        'created_at',
                        'ai_document_files',
                        'received_mail',
                        'transaction',
                    ],
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
                'links' => [
                    'first',
                    'last',
                    'next',
                    'prev',
                ],
            ])
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_applies_date_range_filter(): void
    {
        $user = User::factory()->create();

        AiDocument::factory()->for($user)->create([
            'created_at' => now()->subDays(120),
        ]);

        $recent = AiDocument::factory()->for($user)->create([
            'created_at' => now()->subDays(20),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.index', [
                'date_from' => now()->subDays(90)->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $recent->id);
    }

    public function test_index_keeps_existing_non_date_filters_for_compatibility(): void
    {
        $user = User::factory()->create();

        $matching = AiDocument::factory()->for($user)->create([
            'status' => 'processing',
            'source_type' => 'manual_upload',
            'custom_prompt' => 'Coffee receipt prompt',
        ]);

        AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'source_type' => 'received_email',
            'custom_prompt' => 'Different content',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.index', [
                'status' => 'processing',
                'source_type' => 'manual_upload',
                'search' => 'Coffee',
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_index_enriches_matched_entities_for_consistent_table_and_detail_labels(): void
    {
        $user = User::factory()->create();

        $accountFrom = AccountEntity::factory()->for($user)->create([
            'name' => 'Main Checking',
            'config_type' => 'account',
            'config_id' => 1001,
        ]);

        $accountTo = AccountEntity::factory()->for($user)->create([
            'name' => 'Supermarket Ltd.',
            'config_type' => 'payee',
            'config_id' => 1002,
        ]);

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => [
                'transaction_type' => 'withdrawal',
                'config_type' => 'standard',
                'raw' => [
                    'account' => 'Raw account fallback',
                    'payee' => 'Raw payee fallback',
                ],
                'config' => [
                    'account_from_id' => $accountFrom->id,
                    'account_to_id' => $accountTo->id,
                ],
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.documents.index', ['per_page' => 10]));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.processed_transaction_data.matched_entities.account.name', 'Main Checking')
            ->assertJsonPath('data.0.processed_transaction_data.matched_entities.payee.name', 'Supermarket Ltd.')
            ->assertJsonPath('data.0.processed_transaction_data.matched_entities.account.matched', true)
            ->assertJsonPath('data.0.processed_transaction_data.matched_entities.payee.matched', true);
    }
}
