<?php

namespace Tests\Feature\API;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestmentApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_delete_own_investment(): void
    {
        Sanctum::actingAs($this->user);

        $investment = $this->createInvestmentForUser($this->user);

        $response = $this->deleteJson(route('api.v1.investments.destroy', $investment));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'investment' => [
                'id' => $investment->id,
            ],
        ]);

        $this->assertDatabaseMissing('investments', [
            'id' => $investment->id,
        ]);
    }

    public function test_cannot_delete_other_users_investment(): void
    {
        $otherUser = User::factory()->create();
        $investment = $this->createInvestmentForUser($this->user);

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson(route('api.v1.investments.destroy', $investment));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('investments', [
            'id' => $investment->id,
            'user_id' => $this->user->id,
        ]);
    }

    private function createInvestmentForUser(User $user): Investment
    {
        $currency = $user->currencies()->first() ?? Currency::factory()->for($user)->create();
        $investmentGroup = $user->investmentGroups()->first() ?? InvestmentGroup::factory()->for($user)->create();

        return Investment::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);
    }
}
