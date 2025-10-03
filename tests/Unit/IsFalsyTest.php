<?php

namespace Tests\Unit;

use App\Rules\IsFalsy;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IsFalsyTest extends TestCase
{
    public function test_fails_for_truthy_values_via_validator()
    {
        $validator = Validator::make(['agree' => true], ['agree' => [new IsFalsy()]]);

        $this->assertTrue($validator->fails());
    }

    public function test_passes_for_falsy_values_via_validator()
    {
        $validator = Validator::make(['agree' => false], ['agree' => [new IsFalsy()]]);

        $this->assertFalse($validator->fails());
    }

    public function test_validate_method_calls_fail_callback()
    {
        $rule = new IsFalsy();

        $failed = false;

        $fail = function ($message = null) use (&$failed) {
            $failed = true;
        };

        $rule->validate('agree', true, $fail);

        $this->assertTrue($failed);

        $failed = false;
        $rule->validate('agree', false, $fail);
        $this->assertFalse($failed);
    }
}
