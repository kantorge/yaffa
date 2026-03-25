<?php

namespace Tests\Unit\Services;

use App\Services\AiPromptBuilder;
use PHPUnit\Framework\TestCase;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class AiPromptBuilderTest extends TestCase
{
    public function test_build_main_extraction_prompt_includes_document_text_and_custom_prompt(): void
    {
        $builder = new AiPromptBuilder();

        $prompt = $builder->buildMainExtractionPrompt(
            'Receipt total: 4.50 USD',
            'Prefer merchant name from header'
        );

        $this->assertStringContainsString('Receipt total: 4.50 USD', $prompt);
        $this->assertStringContainsString('Prefer merchant name from header', $prompt);
        $this->assertStringContainsString('The language of the document may vary.', $prompt);
        $this->assertStringContainsString('FOR STANDARD TRANSACTIONS', $prompt);
        $this->assertStringContainsString('FOR INVESTMENT TRANSACTIONS', $prompt);
    }

    public function test_build_main_extraction_prompt_uses_expected_document_language_when_provided(): void
    {
        $builder = new AiPromptBuilder();

        $prompt = $builder->buildMainExtractionPrompt(
            'Receipt total: 4.50 USD',
            null,
            'Hungarian'
        );

        $this->assertStringContainsString('The document provided is expected to be in Hungarian.', $prompt);
        $this->assertStringNotContainsString('The language of the document may vary.', $prompt);
    }

    public function test_build_category_matching_prompt_includes_items_learning_and_categories(): void
    {
        $builder = new AiPromptBuilder();

        $prompt = $builder->buildCategoryMatchingPrompt(
            [
                0 => ['description' => 'Coffee beans', 'amount' => 12.5],
                1 => ['description' => 'Milk', 'amount' => 3.1],
            ],
            [
                0 => [
                    [
                        'category_id' => 7,
                        'description' => 'coffee',
                    ],
                ],
            ],
            "7: Food > Drinks\n8: Food > Groceries"
        );

        $this->assertStringContainsString('CATEGORY LEARNING PATTERNS', $prompt);
        $this->assertStringContainsString('[0] Coffee beans', $prompt);
        $this->assertStringContainsString('[1] Milk', $prompt);
        $this->assertStringNotContainsString('$12.5', $prompt);
        $this->assertStringNotContainsString('$3.1', $prompt);
        $this->assertStringContainsString('7: Food > Drinks', $prompt);
        $this->assertStringContainsString('Recommended Category 7: coffee', $prompt);
        $this->assertStringNotContainsString('similarity:', $prompt);
        $this->assertStringContainsString('CATEGORY MATCHING RULES:', $prompt);
        $this->assertStringContainsString('The language of the document may vary.', $prompt);
    }

    public function test_build_category_matching_prompt_uses_expected_document_language_when_provided(): void
    {
        $builder = new AiPromptBuilder();

        $prompt = $builder->buildCategoryMatchingPrompt(
            [0 => ['description' => 'tej', 'amount' => 2.3]],
            [],
            '11: Groceries',
            'best_match',
            [],
            'Hungarian'
        );

        $this->assertStringContainsString('The document provided is expected to be in Hungarian.', $prompt);
        $this->assertStringNotContainsString('The language of the document may vary.', $prompt);
    }

    public function test_build_category_matching_prompt_includes_mode_specific_rules(): void
    {
        $builder = new AiPromptBuilder();

        $expectations = [
            'parent_only' => 'Only top-level parent categories are allowed for assignment in this prompt.',
            'parent_preferred' => 'The available category list is intentionally parent-oriented for deterministic matching.',
            'child_only' => 'Only child categories are allowed for assignment in this prompt.',
            'child_preferred' => 'Standalone parent categories may appear only when they have no active child categories.',
        ];

        foreach ($expectations as $mode => $expectedLine) {
            $prompt = $builder->buildCategoryMatchingPrompt(
                [0 => ['description' => 'coffee beans', 'amount' => 12.5]],
                [],
                '7: Food > Drinks',
                $mode,
            );

            $this->assertStringContainsString($expectedLine, $prompt);
        }
    }

    public function test_build_category_matching_prompt_includes_optional_category_description_and_normalizes_multiline_values(): void
    {
        $builder = new AiPromptBuilder();

        $prompt = $builder->buildCategoryMatchingPrompt(
            [0 => ['description' => 'milk', 'amount' => 2.3]],
            [],
            "11: Groceries",
            'best_match',
            [
                [
                    'id' => 11,
                    'full_name' => 'Groceries',
                    'description' => "everyday shopping\nfood and household",
                ],
                [
                    'id' => 12,
                    'full_name' => 'Transport',
                    'description' => null,
                ],
            ],
        );

        $this->assertStringContainsString('11: Groceries (description: everyday shopping | food and household)', $prompt);
        $this->assertStringContainsString('12: Transport', $prompt);
        $this->assertStringNotContainsString('12: Transport (description:', $prompt);
    }

    public function test_build_account_matching_prompt_includes_account_list_and_target_name(): void
    {
        $builder = new AiPromptBuilder();

        $prompt = $builder->buildAccountMatchingPrompt('11: Visa Card', 'visa');

        $this->assertStringContainsString('11: Visa Card', $prompt);
        $this->assertStringContainsString('The account to be identified is: visa', $prompt);
        $this->assertStringContainsString('Return ONLY the numeric ID, or N/A if there is no match.', $prompt);
    }

    public function test_build_payee_and_investment_prompts_include_target_values(): void
    {
        $builder = new AiPromptBuilder();

        $payeePrompt = $builder->buildPayeeMatchingPrompt('21: Coffee Shop', 'coffee shop');
        $investmentPrompt = $builder->buildInvestmentMatchingPrompt('42: Apple Inc. (AAPL)', 'AAPL');

        $this->assertStringContainsString('The payee to be identified is: coffee shop', $payeePrompt);
        $this->assertStringContainsString('21: Coffee Shop', $payeePrompt);
        $this->assertStringContainsString('The investment to be identified is: AAPL', $investmentPrompt);
        $this->assertStringContainsString('42: Apple Inc. (AAPL)', $investmentPrompt);
    }

    public function test_build_prompt_message_chain_includes_history_and_current_prompt(): void
    {
        $builder = new AiPromptBuilder();

        $messages = $builder->buildPromptMessageChain('Current prompt', [
            [
                'prompt' => 'First prompt',
                'response' => 'First response',
            ],
            [
                'prompt' => 'Second prompt',
                'response' => 'Second response',
            ],
        ]);

        $this->assertCount(5, $messages);

        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        /** @var UserMessage $firstUserMessage */
        $firstUserMessage = $messages[0];
        $this->assertSame('First prompt', $firstUserMessage->content);

        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        /** @var AssistantMessage $firstAssistantMessage */
        $firstAssistantMessage = $messages[1];
        $this->assertSame('First response', $firstAssistantMessage->content);

        $this->assertInstanceOf(UserMessage::class, $messages[2]);
        /** @var UserMessage $secondUserMessage */
        $secondUserMessage = $messages[2];
        $this->assertSame('Second prompt', $secondUserMessage->content);

        $this->assertInstanceOf(AssistantMessage::class, $messages[3]);
        /** @var AssistantMessage $secondAssistantMessage */
        $secondAssistantMessage = $messages[3];
        $this->assertSame('Second response', $secondAssistantMessage->content);

        $this->assertInstanceOf(UserMessage::class, $messages[4]);
        /** @var UserMessage $currentPromptMessage */
        $currentPromptMessage = $messages[4];
        $this->assertSame('Current prompt', $currentPromptMessage->content);
    }

    public function test_build_prompt_message_chain_skips_empty_history_values(): void
    {
        $builder = new AiPromptBuilder();

        $messages = $builder->buildPromptMessageChain('Current prompt', [
            [
                'prompt' => ' ',
                'response' => "\n\t",
            ],
            [
                'prompt' => 'History prompt',
            ],
            [
                'response' => 'History response',
            ],
        ]);

        $this->assertCount(3, $messages);

        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        /** @var UserMessage $historyPromptMessage */
        $historyPromptMessage = $messages[0];
        $this->assertSame('History prompt', $historyPromptMessage->content);

        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        /** @var AssistantMessage $historyResponseMessage */
        $historyResponseMessage = $messages[1];
        $this->assertSame('History response', $historyResponseMessage->content);

        $this->assertInstanceOf(UserMessage::class, $messages[2]);
        /** @var UserMessage $currentMessage */
        $currentMessage = $messages[2];
        $this->assertSame('Current prompt', $currentMessage->content);
    }

    public function test_build_prompt_message_chain_skips_entries_with_include_in_prompt_history_false(): void
    {
        $builder = new AiPromptBuilder();

        $messages = $builder->buildPromptMessageChain('Current prompt', [
            [
                'prompt' => 'Normal prompt',
                'response' => 'Normal response',
            ],
            [
                'prompt' => 'Local audit prompt',
                'response' => 'Local audit response',
                'include_in_prompt_history' => false,
            ],
            [
                'prompt' => 'Another normal prompt',
                'response' => 'Another normal response',
            ],
        ]);

        $this->assertCount(5, $messages);

        $this->assertInstanceOf(UserMessage::class, $messages[0]);
        /** @var UserMessage $firstMessage */
        $firstMessage = $messages[0];
        $this->assertSame('Normal prompt', $firstMessage->content);

        $this->assertInstanceOf(AssistantMessage::class, $messages[1]);
        /** @var AssistantMessage $secondMessage */
        $secondMessage = $messages[1];
        $this->assertSame('Normal response', $secondMessage->content);

        $this->assertInstanceOf(UserMessage::class, $messages[2]);
        /** @var UserMessage $thirdMessage */
        $thirdMessage = $messages[2];
        $this->assertSame('Another normal prompt', $thirdMessage->content);

        $this->assertInstanceOf(AssistantMessage::class, $messages[3]);
        /** @var AssistantMessage $fourthMessage */
        $fourthMessage = $messages[3];
        $this->assertSame('Another normal response', $fourthMessage->content);

        $this->assertInstanceOf(UserMessage::class, $messages[4]);
        /** @var UserMessage $currentMessage */
        $currentMessage = $messages[4];
        $this->assertSame('Current prompt', $currentMessage->content);
    }
}
