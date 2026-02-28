<?php

namespace Tests\Unit\Services;

use App\Services\AiPromptBuilder;
use PHPUnit\Framework\TestCase;

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
        $this->assertStringContainsString('FOR STANDARD TRANSACTIONS', $prompt);
        $this->assertStringContainsString('FOR INVESTMENT TRANSACTIONS', $prompt);
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
                        'recommended_category_id' => 7,
                        'description' => 'coffee',
                        'similarity' => 0.95,
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
    }

    public function test_build_account_matching_prompt_includes_account_list_and_target_name(): void
    {
        $builder = new AiPromptBuilder();

        $prompt = $builder->buildAccountMatchingPrompt('11: Visa Card', 'visa');

        $this->assertStringContainsString('11: Visa Card', $prompt);
        $this->assertStringContainsString('The account mentioned in the document is: visa', $prompt);
        $this->assertStringContainsString('Please provide ONLY the numeric ID, or N/A', $prompt);
    }

    public function test_build_payee_and_investment_prompts_include_target_values(): void
    {
        $builder = new AiPromptBuilder();

        $payeePrompt = $builder->buildPayeeMatchingPrompt('21: Coffee Shop', 'coffee shop');
        $investmentPrompt = $builder->buildInvestmentMatchingPrompt('42: Apple Inc. (AAPL)', 'AAPL');

        $this->assertStringContainsString('The payee mentioned in the document is: coffee shop', $payeePrompt);
        $this->assertStringContainsString('21: Coffee Shop', $payeePrompt);
        $this->assertStringContainsString('The investment mentioned in the document is: AAPL', $investmentPrompt);
        $this->assertStringContainsString('42: Apple Inc. (AAPL)', $investmentPrompt);
    }
}
