<?php

namespace Tests\Unit\Services;

use App\Exceptions\AiProviderFailureException;
use App\Exceptions\AiResponseParseException;
use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use App\Models\User;
use App\Services\AiPromptBuilder;
use App\Services\AiStepGateway;
use App\Services\ProcessingHistoryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Prism\Prism\Schema\ObjectSchema;
use RuntimeException;
use Tests\TestCase;

class AiStepGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_main_data_returns_structured_payload_and_records_history(): void
    {
        $user = User::factory()->create();
        $config = AiProviderConfig::factory()->for($user)->create([
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
        ]);
        $document = AiDocument::factory()->for($user)->create();

        $gateway = new class (new AiPromptBuilder(), new ProcessingHistoryRecorder()) extends AiStepGateway {
            protected function executeStructuredRequest(
                AiProviderConfig $config,
                AiDocument $document,
                string $prompt,
                bool $promptChatHistoryEnabled,
                ObjectSchema $schema,
            ): array {
                return [
                    'structured' => [
                        'transaction_type' => 'withdrawal',
                        'account' => null,
                        'account_from' => null,
                        'account_to' => null,
                        'payee' => null,
                        'date' => '2026-03-01',
                        'amount' => 10.0,
                        'currency' => 'USD',
                        'transaction_items' => [],
                        'investment' => null,
                        'quantity' => null,
                        'price' => null,
                        'commission' => null,
                        'tax' => null,
                        'dividend' => null,
                    ],
                    'text' => 'ignored',
                ];
            }
        };

        $result = $gateway->extractMainData($config, $document, 'Prompt', true);

        $this->assertSame('withdrawal', $result['transaction_type']);

        $document->refresh();
        $this->assertIsArray($document->ai_chat_history);
        $this->assertCount(1, $document->ai_chat_history);
        $this->assertSame('main_extraction', $document->ai_chat_history[0]['step']);
    }

    public function test_match_categories_batch_returns_matches_and_records_history(): void
    {
        $user = User::factory()->create();
        $config = AiProviderConfig::factory()->for($user)->create([
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
        ]);
        $document = AiDocument::factory()->for($user)->create();

        $gateway = new class (new AiPromptBuilder(), new ProcessingHistoryRecorder()) extends AiStepGateway {
            protected function executeStructuredRequest(
                AiProviderConfig $config,
                AiDocument $document,
                string $prompt,
                bool $promptChatHistoryEnabled,
                ObjectSchema $schema,
            ): array {
                return [
                    'structured' => [
                        'matches' => [
                            [
                                'item_index' => 0,
                                'recommended_category_id' => 12,
                                'confidence_score' => 0.88,
                            ],
                        ],
                    ],
                    'text' => 'ignored',
                ];
            }
        };

        $result = $gateway->matchCategoriesBatch($config, $document, 'Prompt', true);

        $this->assertCount(1, $result);
        $this->assertSame(12, $result[0]['recommended_category_id']);

        $document->refresh();
        $this->assertIsArray($document->ai_chat_history);
        $this->assertCount(1, $document->ai_chat_history);
        $this->assertSame('category_batch_matching', $document->ai_chat_history[0]['step']);
    }

    public function test_match_account_id_wraps_provider_failures_with_metadata(): void
    {
        $user = User::factory()->create();
        $config = AiProviderConfig::factory()->for($user)->create([
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
        ]);
        $document = AiDocument::factory()->for($user)->create();

        $gateway = new class (new AiPromptBuilder(), new ProcessingHistoryRecorder()) extends AiStepGateway {
            protected function executeTextRequest(
                AiProviderConfig $config,
                AiDocument $document,
                string $prompt,
                bool $promptChatHistoryEnabled,
            ): string {
                throw new RuntimeException('cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received');
            }
        };

        try {
            $gateway->matchAccountId($config, $document, 'Prompt', true);
            $this->fail('Expected AiProviderFailureException was not thrown.');
        } catch (AiProviderFailureException $exception) {
            $this->assertSame('account_matching', $exception->step());
            $this->assertSame('openai', $exception->provider());
            $this->assertSame('gpt-4.1-mini', $exception->model());
            $this->assertTrue($exception->isTimeout());
            $this->assertStringContainsString('AI provider error', $exception->getMessage());
        }
    }

    public function test_match_categories_batch_throws_parse_exception_for_invalid_structured_shape(): void
    {
        $user = User::factory()->create();
        $config = AiProviderConfig::factory()->for($user)->create([
            'provider' => 'openai',
            'model' => 'gpt-4.1-mini',
        ]);
        $document = AiDocument::factory()->for($user)->create();

        $gateway = new class (new AiPromptBuilder(), new ProcessingHistoryRecorder()) extends AiStepGateway {
            protected function executeStructuredRequest(
                AiProviderConfig $config,
                AiDocument $document,
                string $prompt,
                bool $promptChatHistoryEnabled,
                ObjectSchema $schema,
            ): array {
                return [
                    'structured' => [
                        'matches' => 'invalid',
                    ],
                    'text' => 'ignored',
                ];
            }
        };

        $this->expectException(AiResponseParseException::class);
        $this->expectExceptionMessage('matches array');

        $gateway->matchCategoriesBatch($config, $document, 'Prompt', true);
    }
}
