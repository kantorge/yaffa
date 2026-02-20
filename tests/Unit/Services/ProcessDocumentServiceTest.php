<?php

namespace Tests\Unit\Services;

use App\Models\AiProviderConfig;
use App\Models\Category;
use App\Models\User;
use App\Services\AssetMatchingService;
use App\Services\CategoryLearningService;
use App\Services\ProcessDocumentService;
use App\Services\TextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_category_matches_are_stored_as_recommendations(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create([
            'active' => 1,
        ]);
        $config = AiProviderConfig::factory()->for($user)->create();

        $service = new class (
            $this->createMock(TextExtractionService::class),
            $this->createMock(AssetMatchingService::class),
            $this->createMock(CategoryLearningService::class),
            $category->id
        ) extends ProcessDocumentService {
            public function __construct(
                TextExtractionService $textExtractor,
                AssetMatchingService $assetMatchingService,
                CategoryLearningService $categoryLearningService,
                private int $categoryId
            ) {
                parent::__construct($textExtractor, $assetMatchingService, $categoryLearningService);
            }

            public function matchCategories(array $items, User $user, AiProviderConfig $config): array
            {
                return $this->matchCategoriesForItems($items, $user, $config);
            }

            protected function callAi(AiProviderConfig $config, string $prompt): string
            {
                return json_encode([
                    [
                        'item_index' => 0,
                        'recommended_category_id' => $this->categoryId,
                        'confidence_score' => 0.4,
                    ],
                ]);
            }
        };

        $result = $service->matchCategories([
            ['description' => 'Coffee beans', 'amount' => 12.5],
        ], $user, $config);

        $this->assertCount(1, $result);
        $this->assertSame(12.5, $result[0]['amount']);
        $this->assertSame('Coffee beans', $result[0]['description']);
        $this->assertSame('ai', $result[0]['match_type']);
        $this->assertSame(0.4, $result[0]['confidence_score']);
        $this->assertSame($category->id, $result[0]['recommended_category_id']);
    }
}
