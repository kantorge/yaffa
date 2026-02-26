<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AiDocumentShowViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_document_show_includes_transfer_payload(): void
    {
        $user = User::factory()->create();

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'source_type' => 'manual_upload',
            'processed_at' => now(),
            'processed_transaction_data' => [
                'raw' => [
                    'transaction_type' => 'transfer',
                    'account_from' => 'Savings Account',
                    'account_to' => 'Checking Account',
                    'date' => '2026-02-01',
                    'amount' => 120.55,
                    'currency' => 'USD',
                ],
                'date' => '2026-02-01',
                'config_type' => 'standard',
                'transaction_type' => 'transfer',
                'config' => [
                    'amount_from' => 120.55,
                    'amount_to' => 120.55,
                    'account_from_id' => null,
                    'account_to_id' => null,
                ],
                'transaction_items' => [],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('ai-documents.show', $document))
            ->assertStatus(Response::HTTP_OK)
            ->assertViewIs('ai-documents.show');

        $response->assertSee('Savings Account', false);
        $response->assertSee('Checking Account', false);
        $response->assertSee('USD', false);
    }

    public function test_ai_document_show_includes_investment_payload(): void
    {
        $user = User::factory()->create();

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'source_type' => 'manual_upload',
            'processed_at' => now(),
            'processed_transaction_data' => [
                'raw' => [
                    'transaction_type' => 'buy',
                    'account' => 'Brokerage Account',
                    'investment' => 'ACME Corp',
                    'date' => '2026-02-02',
                    'amount' => 1000,
                    'quantity' => 10,
                    'price' => 100,
                    'commission' => 2,
                    'tax' => 1,
                    'dividend' => null,
                    'currency' => 'USD',
                ],
                'date' => '2026-02-02',
                'config_type' => 'investment',
                'transaction_type' => 'buy',
                'config' => [
                    'account_id' => null,
                    'investment_id' => null,
                    'quantity' => 10,
                    'price' => 100,
                    'commission' => 2,
                    'tax' => 1,
                    'dividend' => null,
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('ai-documents.show', $document))
            ->assertStatus(Response::HTTP_OK)
            ->assertViewIs('ai-documents.show');

        $response->assertSee('Brokerage Account', false);
        $response->assertSee('ACME Corp', false);
        $response->assertSee('USD', false);
    }
}
