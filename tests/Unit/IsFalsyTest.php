<?php

use App\Rules\IsFalsy;
use Illuminate\Support\Facades\Validator;

it('fails for truthy and passes for falsy values', function (mixed $value, bool $fails) {
    $validator = Validator::make(['agree' => $value], ['agree' => [new IsFalsy()]]);

    expect($validator->fails())->toBe($fails);
})->with([
    'true' => [true, true],
    'false' => [false, false],
]);
