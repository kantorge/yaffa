<?php

namespace Tests\Unit\Services;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use App\Services\InvestmentGroupService;
use Exception;
use Tests\TestCase;

class InvestmentGroupServiceTest extends TestCase
{
    /** @test */
    public function deletesInvestmentGroupSuccessfully(): void
    {
        $investmentGroup = InvestmentGroup::factory()->create();

        $service = new InvestmentGroupService();
        $result = $service->delete($investmentGroup);

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
        $this->assertDatabaseMissing('investment_groups', ['id' => $investmentGroup->id]);
    }

    /** @test */
    public function doesNotDeleteInvestmentGroupInUse(): void
    {
        $user = User::factory()->create();

        $investmentGroup = InvestmentGroup::factory()->for($user)->create();
        Currency::factory()->for($user)->create();
        Investment::factory()->for($user)->for($investmentGroup)->create();

        $service = new InvestmentGroupService();
        $result = $service->delete($investmentGroup);

        $this->assertFalse($result['success']);
        $this->assertEquals(__('Investment group is in use, cannot be deleted'), $result['error']);
        $this->assertDatabaseHas('investment_groups', ['id' => $investmentGroup->id]);
    }
}
