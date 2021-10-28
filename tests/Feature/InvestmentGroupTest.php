<?php

namespace Tests\Feature;

use Tests\TestCase;

class InvestmentGroupTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('investment-group');
        $this->setBaseModel(\App\Models\InvestmentGroup::class);
    }

    /** @test */
    public function user_can_view_list_of_investment_groups()
    {
        $response = $this->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.index");
        $response->assertSeeText('List of investment groups');
    }

    /** @test */
    public function user_cannot_create_an_investment_group_with_missing_data()
    {
        $response = $this->postJson(route("{$this->base_route}.store"), ['name' => '']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_create_an_investment_group()
    {
        $this->create();
    }

    /** @test */
    public function user_can_edit_an_existing_investment_group()
    {
        $model = $this->base_model;
        $response = $this->get(route("{$this->base_route}.edit", $model::inRandomOrder()->first()->id));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.form");
        //TODO: is actual message need to be tested?
        $response->assertSeeText('Modify investment group');
    }

    /** @test */
    public function user_cannot_update_an_investment_group_with_missing_data()
    {
        $model = $this->base_model;
        $item = $model::inRandomOrder()->first()->id;

        $response = $this->patchJson(route("{$this->base_route}.update", $item),
            [
                'id' => $item,
                'name' => '',
            ]
        );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_update_an_investment_group_with_proper_data()
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
        $response->assertSessionHas('notification_collection.0.message', 'Investment group updated');
    }

    /** @test */
    public function user_can_delete_an_existing_investment_group()
    {
        $this->destroy();
    }
}
