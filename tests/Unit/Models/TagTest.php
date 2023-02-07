<?php

namespace Tests\Unit\Models;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_tag_active_scope_returns_only_active_items()
    {
        // Create a user
        $user = User::factory()->create();

        // Create random number of active tags
        $active = $this->createForUser(
            $user,
            Tag::class,
            [
                'active' => true,
            ],
            rand(5, 10)
        );

        // Create random number of inactive tags
        $inactive = $this->createForUser(
            $user,
            Tag::class,
            [
                'active' => false,
            ],
            rand(1, 4)
        );

        // Get active tags
        $result = $user->tags()->active()->get();

        // Compare returned element count
        $this->assertEquals($active->count(), $result->count());
        $this->assertEquals($active->count(), $result->where('active', true)->count());
    }
}
