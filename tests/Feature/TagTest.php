<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\Feature\Concerns\AuthorizesResourceCrud;
use Tests\Feature\Concerns\AuthorizesResourceCrudForUnverifiedUsers;
use Tests\TestCase;

class TagTest extends TestCase
{
    use AuthorizesResourceCrud;
    use AuthorizesResourceCrudForUnverifiedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('tags');
        $this->setBaseModel(Tag::class);
    }

    /**
     * Delete moved to TagApiController (api.v1.tags.destroy) - the web destroy route/action
     * was removed as dead code (T-02, frontend unification). See TagApiControllerTest for
     * delete behavior coverage.
     */
    protected function resourceAuthSupportsDestroy(): bool
    {
        return false;
    }

    public function test_user_can_view_list_of_tags(): void
    {
        $user = User::factory()->create();
        $this->createForUser($user, $this->base_model, [], 5);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs('tags.index');
    }

    public function test_user_can_access_create_form(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('tags.form');
    }

    public function test_user_cannot_create_a_tag_with_missing_data(): void
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

    public function test_user_can_create_a_tag(): void
    {
        $user = User::factory()->create();
        $this->assertCreateForUser($user);
    }

    public function test_user_can_edit_an_existing_tag(): void
    {
        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.edit", $tag));

        $response->assertStatus(200);
        $response->assertViewIs('tags.form');
    }

    public function test_user_cannot_update_a_tag_with_missing_data(): void
    {
        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $tag),
                [
                    'id' => $tag->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_a_tag_with_proper_data(): void
    {
        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);
        $tag2 = $this->rawForUser($user, $this->base_model);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route("{$this->base_route}.update", $tag),
                [
                    'id' => $tag->id,
                    'name' => $tag2['name'],
                ]
            );

        $response->assertRedirect(route("{$this->base_route}.index"));
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }
}
