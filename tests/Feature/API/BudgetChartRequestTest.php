<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create(), ['read']);
});

it('requires an account when the account scope is selected', function () {
    $this->getJson(route('api.v1.reports.budget-chart', ['categories' => [1], 'accountSelection' => 'selected']))
        ->assertJsonValidationErrors('accountEntity');
});
