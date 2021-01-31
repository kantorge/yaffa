<?php

namespace Tests\Feature;

use Tests\TestCase;

class MainTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function user_can_view_main_page()
    {
        $response = $this->get(route("accounts.summary"));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs("accounts.summary");
        $response->assertSeeText('Account summary');
        $response->assertSeeText('Total value');
    }

    /** @test */
    public function user_can_view_account_history()
    {
        $response = $this->get(route("accounts.history", \App\Models\Account::inRandomOrder()->first()->id));
        //$response = $this->get(route("accounts.history", 1));

        $response->assertStatus(200);
        $response->assertViewIs("accounts.history");
        $response->assertSeeText('Account history');
        $response->assertSeeText('Transaction history');
        $response->assertSeeText('Scheduled transactions');
    }

}
