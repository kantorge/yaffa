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

class InvestmentGroupApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_destroysInvestmentGroupSuccessfully(): void
    {
        $investmentGroup = InvestmentGroup::factory()->for($this->user)->create();

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->deleteJson(route('api.v1.investment-groups.destroy', $investmentGroup));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('investment_groups', ['id' => $investmentGroup->id]);
    }

    public function test_doesNotDestroyInvestmentGroupWithoutAuthorization(): void
    {
        $investmentGroup = InvestmentGroup::factory()->create();

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->deleteJson(route('api.v1.investment-groups.destroy', $investmentGroup));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('investment_groups', ['id' => $investmentGroup->id]);
    }

    public function test_doesNotDestroyInvestmentGroupInUse(): void
    {
        $investmentGroup = InvestmentGroup::factory()->for($this->user)->create();
        Currency::factory()->for($this->user)->create();
        Investment::factory()->for($this->user)->for($investmentGroup)->create();

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->deleteJson(route('api.v1.investment-groups.destroy', $investmentGroup));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson(['error' => __('Investment group is in use, cannot be deleted')]);
        $this->assertDatabaseHas('investment_groups', ['id' => $investmentGroup->id]);
    }
}
