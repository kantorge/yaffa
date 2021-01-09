<?php

namespace Tests\Feature;

use Tests\TestCase;

class TransactionStandardTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function user_can_create_a_transactions_with_valid_data()
    {
        $withdrawal = factory(\App\Transaction::class)->states('withdrawal')->create();
        $this->assertDatabaseHas('transactions', [
            'id' => $withdrawal->id,
        ]);
    }

}
