<?php

namespace Tests\Feature;

use App\Models\AccountGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelOwnedByUserTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_user_id_is_not_overwritten_by_authenticated_user(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($authenticatedUser);

        $accountGroup = AccountGroup::factory()->for($otherUser)->create();

        $this->assertSame($otherUser->id, $accountGroup->user_id);
    }

    public function test_user_id_defaults_to_authenticated_user_when_unset(): void
    {
        $authenticatedUser = User::factory()->create();

        $this->actingAs($authenticatedUser);

        $accountGroup = AccountGroup::factory()->make(['user_id' => null]);
        $accountGroup->save();

        $this->assertSame($authenticatedUser->id, $accountGroup->user_id);
    }
}
