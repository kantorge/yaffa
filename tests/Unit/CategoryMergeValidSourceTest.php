<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\User;
use App\Rules\CategoryMergeValidSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CategoryMergeValidSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_when_merging_parent_into_child(): void
    {
        $user = User::factory()->create();

        // Create parent category and a child category
        $parent = Category::factory()->for($user)->create(['parent_id' => null]);
        $child = Category::factory()->for($user)->create(['parent_id' => $parent->id]);

        $data = [
            'category_source' => $parent->id,
            'category_target' => $child->id,
        ];

        $rule = (new CategoryMergeValidSource())->setData($data);

        $validator = Validator::make(['category_source' => $parent->id] + $data, [
            'category_source' => [$rule],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_passes_for_valid_merges(): void
    {
        $user = User::factory()->create();

        // Create a parent and two children (siblings)
        $parent = Category::factory()->for($user)->create(['parent_id' => null]);
        $childA = Category::factory()->for($user)->create(['parent_id' => $parent->id]);
        $childB = Category::factory()->for($user)->create(['parent_id' => $parent->id]);

        // Merging child A into parent should be allowed
        $data = [
            'category_source' => $childA->id,
            'category_target' => $parent->id,
        ];

        $rule = (new CategoryMergeValidSource())->setData($data);

        $validator = Validator::make(['category_source' => $childA->id] + $data, [
            'category_source' => [$rule],
        ]);

        $this->assertFalse($validator->fails());

        // Merging child A into child B (sibling to sibling) should also be allowed
        $data = [
            'category_source' => $childA->id,
            'category_target' => $childB->id,
        ];

        $rule = (new CategoryMergeValidSource())->setData($data);

        $validator = Validator::make(['category_source' => $childA->id] + $data, [
            'category_source' => [$rule],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_validate_method_calls_fail_callback_when_invalid(): void
    {
        $user = User::factory()->create();
        $parent = Category::factory()->for($user)->create(['parent_id' => null]);
        $child = Category::factory()->for($user)->create(['parent_id' => $parent->id]);

        $data = [
            'category_source' => $parent->id,
            'category_target' => $child->id,
        ];

        $rule = (new CategoryMergeValidSource())->setData($data);

        $failed = false;
        $fail = function ($message = null) use (&$failed) {
            $failed = true;
        };

        $rule->validate('category_source', $parent->id, $fail);

        $this->assertTrue($failed);
    }
}
