<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('tag');
        $this->setBaseModel(Tag::class);
    }

    public function test_guest_cannot_access_resource(): void
    {
        $this->get(route("{$this->base_route}.index"))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create"))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store"))->assertRedirect(route('login'));


        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);

        $this->get(route("{$this->base_route}.edit", $tag))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", $tag))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", $tag))->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_access_resource(): void
    {
        /** @var Authenticatable $user_unverified */
        $user_unverified = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.index"))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->get(route("{$this->base_route}.create"))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->post(route("{$this->base_route}.store"))->assertRedirect(route('verification.notice'));


        $user = User::factory()->create();
        $tag = $this->createForUser($user, $this->base_model);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.edit", $tag))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->patch(route("{$this->base_route}.update", $tag))->assertRedirect(route('verification.notice'));
        $this->actingAs($user_unverified)->delete(route("{$this->base_route}.destroy", $tag))->assertRedirect(route('verification.notice'));
    }

    public function test_user_cannot_access_other_users_resource(): void
    {
        $user1 = User::factory()->create();
        $tag = $this->createForUser($user1, $this->base_model);

        /** @var Authenticatable $user2 */
        $user2 = User::factory()->create();

        $this->actingAs($user2)->get(route("{$this->base_route}.edit", $tag))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", $tag))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", $tag))->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_can_view_list_of_tags(): void
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
        $response->assertViewIs("{$this->base_route}.form");
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

        $response->assertRedirect($this->base_route);
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    public function test_user_can_delete_an_existing_tag(): void
    {
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
    }
}
