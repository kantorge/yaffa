<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiUserSettings;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use App\Services\AssetMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_payees_matches_longer_identified_name_to_shorter_stored_payee(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 0.5,
            'asset_max_suggestions' => 10,
        ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Amazon',
                'alias' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Lidl',
                'alias' => null,
            ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchPayees('AMAZON MARKETPLACE EU SARL');

        $this->assertCount(1, $matches);
        $this->assertSame('Amazon', $matches[0]['name']);
    }

    public function test_match_payees_matches_longer_identified_name_to_shorter_stored_alias(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 0.5,
            'asset_max_suggestions' => 10,
        ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Online Shop',
                'alias' => 'Amazon',
            ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchPayees('AMAZON MARKETPLACE EU SARL');

        $this->assertCount(1, $matches);
        $this->assertSame('Online Shop (Amazon)', $matches[0]['name']);
    }

    public function test_match_payees_does_not_match_unrelated_shorter_payee(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 0.5,
            'asset_max_suggestions' => 10,
        ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Lidl',
                'alias' => null,
            ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchPayees('AMAZON MARKETPLACE EU SARL');

        $this->assertCount(0, $matches);
    }

    public function test_match_payees_uses_user_similarity_threshold(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 1.0,
            'asset_max_suggestions' => 10,
        ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Auchan Magyarorszag Kft',
                'alias' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Lidl Magyarorszag',
                'alias' => null,
            ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchPayees('AUCHAN MAGYARORSZAG KFT');

        $this->assertCount(1, $matches);
        $this->assertSame('Auchan Magyarorszag Kft', $matches[0]['name']);
        $this->assertSame(1.0, $matches[0]['similarity']);
    }

    public function test_match_accounts_uses_user_max_suggestions(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 0.0,
            'asset_max_suggestions' => 1,
        ]);

        $expectedTopMatch = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
                'name' => 'Alpha Account',
                'alias' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
                'name' => 'Beta Account',
                'alias' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
                'name' => 'Gamma Account',
                'alias' => null,
            ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchAccounts('alpha');

        $this->assertCount(1, $matches);
        $this->assertSame($expectedTopMatch->id, $matches[0]['id']);
    }

    public function test_match_category_learning_returns_distinct_categories_without_similarity_metadata(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 0.0,
            'asset_max_suggestions' => 10,
        ]);

        $category = Category::factory()->for($user)->create([
            'active' => true,
        ]);

        $user->categoryLearning()->create([
            'item_description' => 'coffee',
            'category_id' => $category->id,
            'usage_count' => 2,
        ]);

        $user->categoryLearning()->create([
            'item_description' => 'coffee beans',
            'category_id' => $category->id,
            'usage_count' => 4,
        ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchCategoryLearning('coffee');

        $this->assertCount(1, $matches);
        $this->assertSame($category->id, $matches[0]['category_id']);
        $this->assertSame('coffee', $matches[0]['description']);
        $this->assertArrayNotHasKey('similarity', $matches[0]);
        $this->assertArrayNotHasKey('category_name', $matches[0]);
    }

    public function test_resolve_category_prompt_context_filters_categories_by_matching_mode(): void
    {
        $user = User::factory()->create();

        $parentWithChild = Category::factory()->for($user)->create([
            'name' => 'Food',
            'active' => true,
            'parent_id' => null,
        ]);

        $child = Category::factory()->for($user)->create([
            'name' => 'Groceries',
            'active' => true,
            'parent_id' => $parentWithChild->id,
        ]);

        $standaloneParent = Category::factory()->for($user)->create([
            'name' => 'Transport',
            'active' => true,
            'parent_id' => null,
        ]);

        $service = new AssetMatchingService($user);

        $parentOnlyContext = $service->resolveCategoryPromptContext($user, 'parent_only');
        $parentPreferredContext = $service->resolveCategoryPromptContext($user, 'parent_preferred');
        $childOnlyContext = $service->resolveCategoryPromptContext($user, 'child_only');
        $childPreferredContext = $service->resolveCategoryPromptContext($user, 'child_preferred');

        $this->assertStringContainsString("{$parentWithChild->id}: {$parentWithChild->full_name}", $parentOnlyContext['categories_list']);
        $this->assertStringContainsString("{$standaloneParent->id}: {$standaloneParent->full_name}", $parentOnlyContext['categories_list']);
        $this->assertStringNotContainsString("{$child->id}: {$child->full_name}", $parentOnlyContext['categories_list']);

        // parent_preferred passes all categories
        $this->assertStringContainsString("{$parentWithChild->id}: {$parentWithChild->full_name}", $parentPreferredContext['categories_list']);
        $this->assertStringContainsString("{$child->id}: {$child->full_name}", $parentPreferredContext['categories_list']);
        $this->assertStringContainsString("{$standaloneParent->id}: {$standaloneParent->full_name}", $parentPreferredContext['categories_list']);

        $this->assertStringContainsString("{$child->id}: {$child->full_name}", $childOnlyContext['categories_list']);
        $this->assertStringNotContainsString("{$parentWithChild->id}: {$parentWithChild->full_name}", $childOnlyContext['categories_list']);
        $this->assertStringNotContainsString("{$standaloneParent->id}: {$standaloneParent->full_name}", $childOnlyContext['categories_list']);

        // child_preferred passes all categories
        $this->assertStringContainsString("{$parentWithChild->id}: {$parentWithChild->full_name}", $childPreferredContext['categories_list']);
        $this->assertStringContainsString("{$child->id}: {$child->full_name}", $childPreferredContext['categories_list']);
        $this->assertStringContainsString("{$standaloneParent->id}: {$standaloneParent->full_name}", $childPreferredContext['categories_list']);
    }

    public function test_resolve_category_prompt_context_falls_back_when_strict_mode_has_no_categories(): void
    {
        $user = User::factory()->create();

        $parent = Category::factory()->for($user)->create([
            'name' => 'Utilities',
            'active' => true,
            'parent_id' => null,
        ]);

        $service = new AssetMatchingService($user);
        $context = $service->resolveCategoryPromptContext($user, 'child_only');

        $this->assertSame('best_match', $context['applied_category_matching_mode']);
        $this->assertTrue($context['used_mode_fallback']);
        $this->assertStringContainsString("{$parent->id}: {$parent->full_name}", $context['categories_list']);
    }
}
