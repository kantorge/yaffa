<?php

namespace Tests\Feature;

use Tests\TestCase;

class TagTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('tags');
        $this->setBaseModel('App\Tag');
    }

    /** @test */
    public function user_can_view_list_of_tags()
    {
        $response = $this->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.index");
        $response->assertSeeText('List of tags');
    }

    /** @test */
    public function user_cannot_create_an_tag_with_missing_data()
    {
        $response = $this->postJson(route("{$this->base_route}.store"), ['name' => '']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_create_an_tag()
    {
        $this->create();
    }

    /** @test */
    public function user_can_edit_an_existing_tag()
    {
        $model = $this->base_model;
        $response = $this->get(route("{$this->base_route}.edit", $model::inRandomOrder()->first()->id));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.form");
        //TODO: is actual message need to be tested?
        $response->assertSeeText('Modify tag');
    }

    /** @test */
    public function user_cannot_update_an_tag_with_missing_data()
    {
        $model = $this->base_model;
        $item = $model::inRandomOrder()->first()->id;

        $response = $this->patchJson(route("{$this->base_route}.update", $item),
            [
                'id' => $item,
                'name' => '' ,
            ]
        );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_update_an_tag_with_proper_data()
    {
        $model = $this->base_model;
        $item = $model::inRandomOrder()->first()->id;

        //$newItem = factory($model)->create();

        $response = $this->patchJson(route("{$this->base_route}.update", $item),
            [
                'id' => $item,
                'name' => 'aa', //TODO: make this dynamic $newItem->name,
            ]
        );

        $response->assertRedirect($this->base_route);
        //TODO: make this dynamic instead of fixed 1st element
        $response->assertSessionHas('notification_collection.0.type', 'success');
        //TODO: is actual message need to be tested?
        $response->assertSessionHas('notification_collection.0.message', 'Tag updated');

    }

    /** @test */
    public function user_can_delete_an_existing_tag()
    {
        $this->destroy();
    }
}
