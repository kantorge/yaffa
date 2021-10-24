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
        $response = $this->get(route('account.summary'));

        $response->assertStatus(200);
        //TODO: should this be a separate variable, e.g. base view
        $response->assertViewIs('account.summary');
        $response->assertSeeText('Account summary');
        $response->assertSeeText('Total value');
    }

    /** @test */
    public function user_can_view_account_history()
    {
        $response = $this->get(route('account.history', \App\Models\Account::inRandomOrder()->first()->id));

        $response->assertStatus(200);
        $response->assertViewIs('account.history');
        $response->assertSeeText('Account history');
        $response->assertSeeText('Transaction history');
        $response->assertSeeText('Scheduled transactions');
    }
}
