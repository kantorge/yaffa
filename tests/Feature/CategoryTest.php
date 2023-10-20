<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('categories');
        $this->setBaseModel(Category::class);
    }

    /** @test */
    public function guest_cannot_access_resource()
    {
        $this->get(route("{$this->base_route}.index"))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create"))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store"))->assertRedirect(route('login'));

        /** @var User $user */
        $user = User::factory()->create();
        /** @var Category $category */
        $category = Category::factory()->for($user)->create();

        $this->get(route("{$this->base_route}.edit", $category))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", $category))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", $category))->assertRedirect(route('login'));
    }

    /** @test */
    public function user_cannot_access_other_users_resource()
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $user2 = User::factory()->create();

        $this->actingAs($user2)->get(route("{$this->base_route}.edit", $category->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", $category->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", $category->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function user_can_view_list_of_categories()
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->count(5)->create();

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.index");
    }

    /** @test */
    public function user_cannot_create_a_category_with_missing_data()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store"),
                [
                    'name' => '',
                    'active' => 1,
                    'parent_id' => null,
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_access_create_form()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function user_can_create_a_category()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertCreateForUser($user);
    }

    /** @test */
    public function user_can_edit_an_existing_category()
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    "{$this->base_route}.edit",
                    $category->id
                )
            );

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function user_cannot_update_a_category_with_missing_data()
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $category->id
                ),
                [
                    'name' => '',
                    'active' => $category->active,
                    'parent_id' => $category->parent_id,
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_update_a_category_with_proper_data()
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $attributes = Category::factory()->for($user)->raw();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $category->id
                ),
                [
                    'name' => $attributes['name'],
                    'active' => $category->active,
                    'parent_id' => $category->parent_id,
                ]
            );

        $response->assertRedirect(route("{$this->base_route}.index"));
        //TODO: make this dynamic instead of fixed 1st element
        $response->assertSessionHas('notification_collection.0.type', 'success');
    }

    /** @test */
    public function user_can_delete_an_existing_category()
    {
        $user = User::factory()->create();
        $this->assertDestroyWithUser($user);
    }
}
