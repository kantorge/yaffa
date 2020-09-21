<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AccountGroupControllerTest extends TestCase
{

    use DatabaseMigrations;

    public function testIndex()
    {

        $response = $this->get('accountgroups');

        $response->assertStatus(200)->assertViewHas('accountGroups');
    }

    public function store_validates_using_a_form_request()
    {
        $this->assertActionUsesFormRequest(
            AccountGroupController::class,
            'store',
            AccountGroupRequest::class
        );
    }
}
