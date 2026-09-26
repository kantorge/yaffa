<?php

namespace Tests\Feature;

use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\Feature\Concerns\AuthorizesResourceCrud;
use Tests\Feature\Concerns\AuthorizesResourceCrudForUnverifiedUsers;
use Tests\TestCase;

class InvestmentGroupTest extends TestCase
{
    use AuthorizesResourceCrud;
    use AuthorizesResourceCrudForUnverifiedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('investment-groups');
        $this->setBaseModel(InvestmentGroup::class);
    }

    /**
     * Delete moved to InvestmentGroupApiController (api.v1.investment-groups.destroy) - the
     * web destroy route/action was removed as dead code. See InvestmentGroupApiControllerTest
     * for delete behavior coverage.
     */
    protected function resourceAuthSupportsDestroy(): bool
    {
        return false;
    }

    public function test_user_can_view_list_of_investment_groups(): void
    {
        $user = User::factory()->create();

        $this->createForUser($user, $this->base_model, [], 5);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs('investment-groups.index');
    }

    public function test_user_can_access_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('investment-groups.form');
    }

    public function test_user_cannot_create_an_investment_group_with_missing_data(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store"),
                [
                    'name' => '',
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_create_an_investment_group(): void
    {
        $user = User::factory()->create();
        $this->assertCreateForUser($user);
    }

    public function test_user_can_edit_an_existing_investment_group(): void
    {
        $user = User::factory()->create();

        $investmentGroup = $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.edit", $investmentGroup));

        $response->assertStatus(200);
        $response->assertViewIs('investment-groups.form');
    }

    public function test_user_cannot_update_an_investment_group_with_missing_data(): void
    {
        $user = User::factory()->create();

        $investmentGroup = $this->createForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $investmentGroup),
                [
                    'id' => $investmentGroup->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_an_investment_group_with_proper_data(): void
    {
        $user = User::factory()->create();

        $investmentGroup = $this->createForUser($user, $this->base_model);
        $investmentGroup2 = $this->rawForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $investmentGroup),
                [
                    'id' => $investmentGroup->id,
                    'name' => $investmentGroup2['name'],
                ]
            );

        $response->assertRedirect($this->base_route);
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

}
