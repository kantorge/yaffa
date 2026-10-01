<?php

use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvestmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('summary defaults to active investments and scopes every filter to its owner', function () {
    $user = User::factory()->create();
    $active = Investment::factory()->withUser($user)->create(['active' => true]);
    $inactive = Investment::factory()->withUser($user)->create(['active' => false]);
    Investment::factory()->create(['active' => true]);
    Sanctum::actingAs($user, ['read']);

    $this->getJson(route('api.v1.investments.summary'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.quantity', 0)->assertJsonPath('data.0.price', null);
    $this->getJson(route('api.v1.investments.summary', ['active' => '0']))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inactive->id);
    $this->getJson(route('api.v1.investments.summary', ['active' => 'all']))->assertOk()
        ->assertJsonCount(2, 'data');
    $this->getJson(route('api.v1.investments.summary', ['active' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors('active');
});

test('summary requires authentication and read ability', function () {
    $this->getJson(route('api.v1.investments.summary'))->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create(), ['write']);
    $this->getJson(route('api.v1.investments.summary'))->assertForbidden();
});

test('summary preserves holdings and latest price semantics without counting schedules', function (?string $storedDate, float $expectedPrice) {
    $user = User::factory()->create();
    $investment = Investment::factory()->withUser($user)->create(['active' => true]);
    $attributes = ['investment_id' => $investment->id, 'quantity' => 10, 'price' => 12.3456789012];
    Transaction::factory()->buy($user, $attributes)->create(['date' => '2025-01-10']);
    Transaction::factory()->sell($user, array_merge($attributes, ['quantity' => 3, 'price' => null]))
        ->create(['date' => '2025-01-11']);
    Transaction::factory()->add_shares($user, array_merge($attributes, ['quantity' => 2, 'price' => null]))
        ->create(['date' => '2025-01-12']);
    Transaction::factory()->remove_shares($user, array_merge($attributes, ['quantity' => 1, 'price' => null]))
        ->create(['date' => '2025-01-13']);
    Transaction::factory()->buy_schedule($user, array_merge($attributes, ['quantity' => 100, 'price' => 99]))
        ->create(['date' => '2025-02-01']);
    if ($storedDate !== null) {
        InvestmentPrice::factory()->for($investment)->create(['date' => $storedDate, 'price' => 20]);
    }
    Sanctum::actingAs($user, ['read']);

    $response = $this->getJson(route('api.v1.investments.summary'))->assertOk();

    expect($response->json('data.0.quantity'))->toEqual(8)
        ->and($response->json('data.0.price'))->toEqual($expectedPrice)
        ->and($response->json('data.0.transactions_count'))->toBe(5);
})->with([
    'newer stored price' => ['2025-01-20', 20.0],
    'newer transaction price' => ['2025-01-01', 12.3456789012],
    'transaction wins same date' => ['2025-01-10', 12.3456789012],
    'transaction only' => [null, 12.3456789012],
]);

test('summary uses stored prices for investments without transactions', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->withUser($user)->create(['active' => true]);
    InvestmentPrice::factory()->for($investment)->create(['date' => '2025-01-10', 'price' => 25]);
    InvestmentPrice::factory()->for($investment)->create(['date' => '2025-01-01', 'price' => 10]);
    Sanctum::actingAs($user, ['read']);

    $this->getJson(route('api.v1.investments.summary'))->assertOk()
        ->assertJsonPath('data.0.price', 25)->assertJsonPath('data.0.quantity', 0);
});

test('summary query count stays constant as investments are added', function () {
    $user = User::factory()->create();
    Investment::factory()->withUser($user)->create(['active' => true]);
    $service = app(InvestmentService::class);

    DB::enableQueryLog();
    $service->getSummary($user)->toJson();
    $initialCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    Investment::factory()->withUser($user)->count(15)->create(['active' => true]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $summary = $service->getSummary($user);
    $summary->toJson();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($summary)->toHaveCount(16)
        ->and($queryCount)->toBe($initialCount)->toBe(3);
});

test('investment page renders without calculating holdings', function () {
    $user = User::factory()->create();
    Investment::factory()->withUser($user)->create(['active' => true, 'name' => 'Deferred investment']);
    $this->mock(InvestmentService::class, function ($mock): void {
        $mock->shouldNotReceive('getLatestPrice');
        $mock->shouldNotReceive('getCurrentQuantity');
        $mock->shouldNotReceive('getSummary');
    });

    $this->actingAs($user)->get(route('investments.index'))->assertOk()
        ->assertViewIs('investments.index')->assertDontSee('Deferred investment');
});
