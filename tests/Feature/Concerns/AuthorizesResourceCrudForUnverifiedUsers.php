<?php

namespace Tests\Feature\Concerns;

use App\Models\User;

/**
 * Shared "unverified user cannot access resource" CRUD authorization test.
 *
 * Reuses the resourceAuth*() hooks from AuthorizesResourceCrud - always use both traits
 * together. Only opted into by resource test classes that already assert this behavior.
 */
trait AuthorizesResourceCrudForUnverifiedUsers
{
    public function test_unverified_user_cannot_access_resource(): void
    {
        $collectionParams = $this->resourceAuthCollectionRouteParams();

        $user_unverified = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.index", $collectionParams))->assertRedirectToRoute('verification.notice');
        $this->actingAs($user_unverified)->get(route("{$this->base_route}.create", $collectionParams))->assertRedirectToRoute('verification.notice');
        $this->actingAs($user_unverified)->post(route("{$this->base_route}.store", $collectionParams))->assertRedirectToRoute('verification.notice');

        $user = User::factory()->create();
        $resource = $this->createResourceForAuthTest($user);
        $memberParams = $this->resourceAuthMemberRouteParams($resource);

        $this->actingAs($user_unverified)->get(route("{$this->base_route}.edit", $memberParams))->assertRedirectToRoute('verification.notice');
        $this->actingAs($user_unverified)->patch(route("{$this->base_route}.update", $memberParams))->assertRedirectToRoute('verification.notice');

        if ($this->resourceAuthSupportsDestroy()) {
            $this->actingAs($user_unverified)->delete(route("{$this->base_route}.destroy", $memberParams))->assertRedirectToRoute('verification.notice');
        }
    }
}
