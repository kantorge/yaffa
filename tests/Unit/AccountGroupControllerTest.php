<?php

namespace Tests\Unit;

use App\Http\Requests\AccountGroupRequest;
//use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AccountGroupControllerTest extends TestCase
{

    //use DatabaseMigrations;

    public function testIndex()
    {

        $response = $this->get('accountgroups');

        $response->assertStatus(200)->assertViewHas('accountGroups');
    }

    /** @test */
    /*
    public function store_should_fail_without_a_name()
    {
       $response = $this
            ->json('POST', '/accountgroups', [
                'name' => 'aaa'
            ]);

        $response->assertStatus(422);
       // $response->assertValidationErrors(['name']);
    }
    */
    /** @test */
    /*
    public function store_new_account_group()
    {
        $accountGroup = factory(AccountGroup::class);

        $request = new AccountGroupRequest($accountGroup->toArray());

        $validator = \Validator::make(
            $accountGroup,
            $request->rules()
        );

        $this->assertTrue($validator->passes());

    }
    */
}
