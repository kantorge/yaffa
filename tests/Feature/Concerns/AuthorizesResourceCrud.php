<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

/**
 * Shared CRUD authorization tests for the resource Feature test classes:
 * - a guest (unauthenticated user) cannot access any of the resource's routes
 * - an authenticated user cannot edit/update/delete another user's resource
 *
 * The consuming TestCase must call setBaseRoute()/setBaseModel() in setUp() as usual.
 * Override the protected hooks below only where a resource's routes/factories genuinely
 * differ from the common case (extra route parameters, no destroy route, a non-trivial
 * factory setup, etc) - do not duplicate the test methods themselves.
 *
 * See AuthorizesResourceCrudForUnverifiedUsers for the companion "unverified user" test,
 * which reuses these same hooks and is opted into separately since not every resource test
 * asserts it.
 */
trait AuthorizesResourceCrud
{
    /**
     * Extra route parameters shared by the index/create/store routes (e.g. ['type' => 'payee']).
     */
    protected function resourceAuthCollectionRouteParams(): array
    {
        return [];
    }

    /**
     * Route parameters for the edit/update/destroy routes, given the resource created by
     * createResourceForAuthTest(). Defaults to passing the model itself (route model binding
     * resolves it via its route key), which covers every resource with a single implicit
     * {resource} route parameter.
     */
    protected function resourceAuthMemberRouteParams(mixed $resource): mixed
    {
        return $resource;
    }

    /**
     * Whether this resource exposes a destroy route to test.
     */
    protected function resourceAuthSupportsDestroy(): bool
    {
        return true;
    }

    /**
     * Create the resource instance owned by the given user, used for the edit/update/destroy checks.
     */
    protected function createResourceForAuthTest(User $user): Model
    {
        return $this->createForUser($user, $this->base_model);
    }

    public function test_guest_cannot_access_resource(): void
    {
        $collectionParams = $this->resourceAuthCollectionRouteParams();

        $this->get(route("{$this->base_route}.index", $collectionParams))->assertRedirectToRoute('login');
        $this->get(route("{$this->base_route}.create", $collectionParams))->assertRedirectToRoute('login');
        $this->post(route("{$this->base_route}.store", $collectionParams))->assertRedirectToRoute('login');

        $user = User::factory()->create();
        $resource = $this->createResourceForAuthTest($user);
        $memberParams = $this->resourceAuthMemberRouteParams($resource);

        $this->get(route("{$this->base_route}.edit", $memberParams))->assertRedirectToRoute('login');
        $this->patch(route("{$this->base_route}.update", $memberParams))->assertRedirectToRoute('login');

        if ($this->resourceAuthSupportsDestroy()) {
            $this->delete(route("{$this->base_route}.destroy", $memberParams))->assertRedirectToRoute('login');
        }
    }

    public function test_user_cannot_access_other_users_resource(): void
    {
        $user1 = User::factory()->create();
        $resource = $this->createResourceForAuthTest($user1);
        $memberParams = $this->resourceAuthMemberRouteParams($resource);

        $user2 = User::factory()->create();

        $this->actingAs($user2)->get(route("{$this->base_route}.edit", $memberParams))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", $memberParams))
            ->assertStatus(Response::HTTP_FORBIDDEN);

        if ($this->resourceAuthSupportsDestroy()) {
            $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", $memberParams))
                ->assertStatus(Response::HTTP_FORBIDDEN);
        }
    }
}
