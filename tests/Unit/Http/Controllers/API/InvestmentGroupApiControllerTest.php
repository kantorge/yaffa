<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Http\Response;
use Tests\TestCase;

class InvestmentGroupApiControllerTest extends TestCase
{
    /** @test */
    public function destroysInvestmentGroupSuccessfully()
    {
        $user = User::factory()->create();
        $investmentGroup = InvestmentGroup::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.investmentgroup.destroy', $investmentGroup));

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('investment_groups', ['id' => $investmentGroup->id]);
    }

    /** @test */
    public function doesNotDestroyInvestmentGroupWithoutAuthorization()
    {
        $user = User::factory()->create();
        $investmentGroup = InvestmentGroup::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.investmentgroup.destroy', $investmentGroup));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('investment_groups', ['id' => $investmentGroup->id]);
    }

    /** @test */
    public function doesNotDestroyInvestmentGroupInUse()
    {
        $user = User::factory()->create();
        $investmentGroup = InvestmentGroup::factory()->for($user)->create();
        Currency::factory()->for($user)->create();
        Investment::factory()->for($user)->for($investmentGroup)->create();

        $response = $this->actingAs($user)
            ->deleteJson(route('api.investmentgroup.destroy', $investmentGroup));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson(['error' => __('Investment group is in use, cannot be deleted')]);
        $this->assertDatabaseHas('investment_groups', ['id' => $investmentGroup->id]);
    }
}
