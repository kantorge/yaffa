<?php

namespace Tests\Feature\API;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class TagApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_active_status_of_a_tag(): void
    {
        // Create a user and a tag
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Tag $tag */
        $tag = Tag::factory()
            ->for($user)
            ->create([
                'active' => false,
            ]);

        $this->actingAs($user);
        $response = $this->patchJson(route('api.v1.tags.patch-active', [
            'tag' => $tag->id,
        ]), [
            'active' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertTrue($tag->fresh()->active);
    }

    public function test_it_throws_an_authorization_exception_if_user_is_not_authorized_to_update_a_tag(): void
    {
        // Create a user and a category
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Tag $tag */
        $tag = Tag::factory()
            ->for($user)
            ->create([
                'active' => false,
            ]);

        // Create a different user
        /** @var User $user2 */
        $user2 = User::factory()->create();

        // Try to update the category as an unauthenticated user
        $response = $this->patchJson(
            route('api.v1.tags.patch-active', [
                'tag' => $tag->id,
            ]),
            ['active' => true],
            [
                'Accept' => 'application/json'
            ]
        );

        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_UNAUTHORIZED),
                $this->equalTo(Response::HTTP_FORBIDDEN)
            )
        );

        $this->assertFalse($tag->fresh()->active);

        // Try to update the category as the different user
        $this->actingAs($user2);
        $response = $this->patchJson(route('api.v1.tags.patch-active', [
            'tag' => $tag->id,
        ]), [
            'active' => true,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertFalse($tag->fresh()->active);
    }
}
