<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Tests\TestCase;

class AiDocumentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_ai_document_pages(): void
    {
        $document = AiDocument::factory()->create();
        $file = AiDocumentFile::factory()->for($document)->create();

        $this->get(route('ai-documents.index'))
            ->assertRedirectToRoute('login');
        $this->get(route('ai-documents.show', $document))
            ->assertRedirectToRoute('login');
        $this->get(route('ai-documents.files.show', [$document, $file]))
            ->assertRedirectToRoute('login');
    }

    public function test_user_can_view_ai_document_index_and_show_pages(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('ai-documents.index'))
            ->assertStatus(Response::HTTP_OK)
            ->assertViewIs('ai-documents.index');

        $this->actingAs($user)
            ->get(route('ai-documents.show', $document))
            ->assertStatus(Response::HTTP_OK)
            ->assertViewIs('ai-documents.show');
    }

    public function test_user_cannot_view_other_users_document(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $document = AiDocument::factory()->for($owner)->create();

        $this->actingAs($viewer)
            ->get(route('ai-documents.show', $document))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_can_preview_ai_document_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        $path = "ai_documents/{$user->id}/{$document->id}/test.txt";
        Storage::disk('local')->put($path, 'Sample');

        $file = AiDocumentFile::factory()->for($document)->create([
            'file_path' => $path,
            'file_name' => 'test.txt',
            'file_type' => 'txt',
        ]);

        $this->actingAs($user)
            ->get(route('ai-documents.files.show', [$document, $file]))
            ->assertStatus(Response::HTTP_OK);
    }

    public function test_user_can_check_ai_document_duplicates(): void
    {
        $user = User::factory()->create();

        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal($user)
            ->create();

        $transaction->load(['config', 'transactionItems']);
        $amount = (float) $transaction->transactionItems->sum('amount');

        $rawData = [
            'date' => $transaction->date?->format('Y-m-d'),
            'amount' => $amount,
            'config_type' => $transaction->config_type,
            'transaction_type' => $transaction->transaction_type->value,
            'account_from_id' => $transaction->config->account_from_id,
            'account_to_id' => $transaction->config->account_to_id,
        ];

        $firstItem = $transaction->transactionItems->first();

        $document = AiDocument::factory()->for($user)->create([
            'status' => 'ready_for_review',
            'source_type' => 'manual_upload',
            'processed_transaction_data' => [
                'raw' => $rawData,
                'date' => $rawData['date'],
                'config_type' => $rawData['config_type'],
                'transaction_type' => $rawData['transaction_type'],
                'config' => [
                    'amount_from' => $amount,
                    'amount_to' => $amount,
                    'account_from_id' => $transaction->config->account_from_id,
                    'account_to_id' => $transaction->config->account_to_id,
                ],
                'items' => [
                    [
                        'amount' => $amount,
                        'category_id' => $firstItem?->category_id ?: null,
                        'match_type' => null,
                        'confidence_score' => null,
                        'description' => $firstItem?->comment ?? '',
                    ],
                ],
            ],
            'processed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('api.v1.documents.checkDuplicates', ['aiDocument' => $document]))
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'id' => $transaction->id,
            ]);
    }
}
