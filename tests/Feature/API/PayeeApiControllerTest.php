<?php

namespace Tests\Feature\API;

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayeeApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_accept_default_category_suggestion_with_other_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $payee = Payee::factory()->withUser($user)->create();
        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        $foreignCategory = Category::factory()->for($otherUser)->create([
            'active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('api.v1.payees.category-suggestions.accept', [
            'accountEntity' => $payeeEntity->id,
            'category' => $foreignCategory->id,
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }
}
