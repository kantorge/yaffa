<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccountGroupTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-group');
        $this->setBaseModel('App\Models\AccountGroup');
    }

    /** @test */
    public function user_can_view_list_of_account_groups()
    {
        $response = $this->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.index");
        $response->assertSeeText('List of account groups');
    }

    /** @test */
    public function user_cannot_create_an_account_group_with_missing_data()
    {
        $response = $this->postJson(route("{$this->base_route}.store"), ['name' => '']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_create_an_account_group()
    {
        $this->create();
    }

    /** @test */
    public function user_can_edit_an_existing_account_group()
    {
        $model = $this->base_model;
        $response = $this->get(route("{$this->base_route}.edit", $model::inRandomOrder()->first()->id));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.form");
        //TODO: is actual message need to be tested?
        $response->assertSeeText('Modify account group');
    }

    /** @test */
    public function user_cannot_update_an_account_group_with_missing_data()
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
    public function user_can_update_an_account_group_with_proper_data()
    {
        $model = $this->base_model;
        $item = $model::inRandomOrder()->first()->id;

        $response = $this->patchJson(route("{$this->base_route}.update", $item),
            [
                'id' => $item,
                'name' => 'aa', //TODO: make this dynamic
            ]
        );
        $response->assertRedirect($this->base_route);
        //TODO: make this dynamic instead of fixed 1st element
        $response->assertSessionHas('notification_collection.0.type', 'success');
        //TODO: is actual message need to be tested?
        $response->assertSessionHas('notification_collection.0.message', 'Account group updated');

    }

    /** @test */
    public function user_can_delete_an_existing_account_group()
    {
        //create an item
        $this->create();
        $model = $this->base_model;
        //select a random item
        $item = $model::inRandomOrder()->first()->id;
        //remove it
        $this->destroy($item);
    }
}
