<?php

namespace Tests\Feature;

use Tests\TestCase;

class CurrencyTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('currencies');
        $this->setBaseModel(\App\Models\Currency::class);
    }

    /** @test */
    public function user_can_view_list_of_currencies()
    {
        $response = $this->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.index");
        $response->assertSeeText('List of currencies');
    }

    /** @test */
    public function user_cannot_create_a_currency_with_missing_data()
    {
        $response = $this->postJson(route("{$this->base_route}.store"), ['name' => '']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_create_a_currency()
    {
        $this->create();
    }

    /** @test */
    public function user_can_edit_an_existing_currency()
    {
        $model = $this->base_model;
        $response = $this->get(route("{$this->base_route}.edit", $model::inRandomOrder()->first()->id));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("{$this->base_route}.form");
        //TODO: is actual message need to be tested?
        $response->assertSeeText('Modify currency');
    }

    /** @test */
    public function user_cannot_update_a_currency_with_missing_data()
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
    public function user_can_update_a_currency_with_proper_data()
    {
        $model = $this->base_model;
        $item = $model::inRandomOrder()
            ->whereNull('base')
            ->first();

        //$newItem = factory($model)->create();

        $response = $this->patchJson(route("{$this->base_route}.update", $item->id),
            [
                'id' => $item->id,
                'name' => 'aa', //TODO: make this dynamic $newItem->name,
                'iso_code' => $item->iso_code,
                'num_digits' => $item->num_digits,
            ]
        );

        $response->assertRedirect($this->base_route);
        //TODO: make this dynamic instead of fixed 1st element
        $response->assertSessionHas('notification_collection.0.type', 'success');
        //TODO: is actual message need to be tested?
        $response->assertSessionHas('notification_collection.0.message', 'Currency updated');

    }

    /** @test */
    public function user_can_delete_an_existing_currency()
    {
        $this->destroy();
    }
}
