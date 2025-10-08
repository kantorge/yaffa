<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class InvestmentGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('investment-group');
        $this->setBaseModel(InvestmentGroup::class);
    }

    public function test_guest_cannot_access_resource(): void
    {
        $this->get(route("{$this->base_route}.index"))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create"))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store"))->assertRedirect(route('login'));


        $user = User::factory()->create();
        $investmentGroup = $this->createForUser($user, $this->base_model);

        $this->get(route("{$this->base_route}.edit", $investmentGroup))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", $investmentGroup))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", $investmentGroup))->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_access_resource(): void
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user_unverified */
        $user_unverified = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.index"))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->get(route("{$this->base_route}.create"))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->post(route("{$this->base_route}.store"))->assertRedirect(route('verification.notice'));


        $user = User::factory()->create();
        $investmentGroup = $this->createForUser($user, $this->base_model);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.edit", $investmentGroup))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->patch(route("{$this->base_route}.update", $investmentGroup))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->delete(route("{$this->base_route}.destroy", $investmentGroup))->assertRedirect(route('verification.notice'));
    }

    public function test_user_cannot_access_other_users_resource(): void
    {
        $user1 = User::factory()->create();
        $investmentGroup = $this->createForUser($user1, $this->base_model);

        $user2 = User::factory()->create();
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user2 */
        $this->actingAs($user2)->get(route("{$this->base_route}.edit", $investmentGroup))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", $investmentGroup))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", $investmentGroup))->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_can_view_list_of_investment_groups(): void
    {
        $user = User::factory()->create();

        $this->createForUser($user, $this->base_model, [], 5);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.index");
    }

    public function test_user_can_access_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs("{$this->base_route}.form");
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
        $response->assertViewIs("{$this->base_route}.form");
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
            ->contains(fn($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    public function test_user_can_delete_an_existing_investment_group(): void
    {
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
    }

    public function test_user_cannot_delete_investment_group_with_attached_investment(): void
    {
        $user = User::factory()->create();

        $investmentGroup = $this->createForUser($user, $this->base_model);
        Currency::factory()->for($user)->create();
        Investment::factory()->for($user)->for($investmentGroup)->create();

        $response = $this->actingAs($user)->deleteJson(route("{$this->base_route}.destroy", $investmentGroup->id));
        $response->assertSessionHas('notification_collection.0.type', 'danger');

        $this->assertDatabaseHas($investmentGroup->getTable(), $investmentGroup->toArray());
    }
}
