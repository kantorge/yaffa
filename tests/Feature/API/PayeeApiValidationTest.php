<?php

namespace Tests\Feature\API;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class PayeeApiValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_create_payee_with_category_both_preferred_and_not_preferred(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $category */
        $category = Category::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.payees.store'), [
            'name' => 'Conflicting Payee',
            'active' => true,
            'config_type' => 'payee',
            'config' => [
                'category_id' => null,
                'preferred' => [$category->id],
                'not_preferred' => [$category->id],
            ],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['config.preferred.0', 'config.not_preferred.0']);

        $this->assertDatabaseMissing('account_entities', ['name' => 'Conflicting Payee']);
        $this->assertDatabaseEmpty('account_entity_category_preference');
    }

    public function test_cannot_update_payee_with_category_both_preferred_and_not_preferred(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Category $existingCategory */
        $existingCategory = Category::factory()->for($user)->create();

        /** @var Category $conflictingCategory */
        $conflictingCategory = Category::factory()->for($user)->create();

        /** @var AccountEntity $payee */
        $payee = AccountEntity::factory()->asPayee($user)->create(['name' => 'Existing Payee']);
        $payee->categoryPreference()->sync([
            $existingCategory->id => ['preferred' => true],
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('api.v1.payees.update', ['accountEntity' => $payee->id]), [
                'name' => 'Existing Payee',
                'config_type' => 'payee',
                'config' => [
                    'category_id' => null,
                    'preferred' => [$conflictingCategory->id],
                    'not_preferred' => [(string) $conflictingCategory->id],
                ],
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['config.preferred.0', 'config.not_preferred.0']);

        $this->assertDatabaseCount('account_entity_category_preference', 1);
        $this->assertDatabaseHas('account_entity_category_preference', [
            'account_entity_id' => $payee->id,
            'category_id' => $existingCategory->id,
            'preferred' => true,
        ]);
    }
}
