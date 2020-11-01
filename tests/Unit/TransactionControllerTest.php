<?php

namespace Tests\Unit;

use App\Http\Requests\TransactionRequest;
use App\Transaction;
use App\TransactionDetailStandard;
use Carbon\Carbon;
//use Illuminate\Foundation\Testing\RefreshDatabase;
//use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    //use DatabaseMigrations;
    //use RefreshDatabase;

    /*
    public function setUp(): void
    {
        parent::setUp();

print_r(\DB::connection()->getDatabaseName());

        //$this->artisan('db:seed');

        $this->seed();

    }
    */

    /** @test */
    public function store_new_transaction_withdrawal()
    {
        $transaction = [
            'date' => Carbon::now(),
            'transaction_type_id' => 1,
            'config_type' => 'transaction_detail_standard',
            'config' => [
                'account_from_id' => 1,
                'account_to_id' => 3,
                'amount_from' => 1,
                'amount_to' => 1,
            ]
        ];

        $request = new TransactionRequest($transaction);

        $validator = \Validator::make(
            $transaction,
            $request->rules()
        );

        $this->assertTrue($validator->passes());

    }

    /** @test */
    public function store_new_transaction_deposit()
    {
        $transaction = [
            'date' => Carbon::now(),
            'transaction_type_id' => 2,
            'config_type' => 'transaction_detail_standard',
            'config' => [
                'account_from_id' => 3,
                'account_to_id' => 1,
                'amount_from' => 1,
                'amount_to' => 1,
            ]
        ];
        //dd(\App\AccountEntity::all()->where('config_type', 'account'));
        $request = new TransactionRequest($transaction);

        $validator = \Validator::make(
            $transaction,
            $request->rules()
        );

        //$this->assertTrue($validator->passes());
        $validator->passes();
        dd($validator->failed());

    }

        /** @test */
    /*
    public function store_validates_using_a_form_request()
    {
        $this->assertActionUsesFormRequest(
            TransactionController::class,
            'store',
            TransactionRequest::class
        () REGIO JÁTÉK!100%!
    }
    */
}
