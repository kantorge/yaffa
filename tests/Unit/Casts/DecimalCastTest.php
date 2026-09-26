<?php

namespace Tests\Unit\Casts;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DecimalCast round-trip and serialization coverage, exercised via CurrencyRate::rate
 * (a ratio, not a currency amount, so it's BigDecimal-cast rather than Money-cast).
 */
class DecimalCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_a_big_decimal_at_the_columns_scale(): void
    {
        $user = User::factory()->create();
        $from = Currency::factory()->fromIsoCodes(['USD'])->for($user)->create();
        $to = Currency::factory()->fromIsoCodes(['EUR'])->for($user)->create();

        $rate = CurrencyRate::factory()->create([
            'from_id' => $from->id,
            'to_id' => $to->id,
            'rate' => '1.5',
        ]);

        $fresh = $rate->fresh();

        $this->assertInstanceOf(BigDecimal::class, $fresh->rate);
        $this->assertSame('1.5000000000', (string) $fresh->rate);
    }

    public function test_set_accepts_a_plain_scalar_and_stores_it_at_the_exact_scale(): void
    {
        $user = User::factory()->create();
        $from = Currency::factory()->fromIsoCodes(['USD'])->for($user)->create();
        $to = Currency::factory()->fromIsoCodes(['EUR'])->for($user)->create();

        $rate = CurrencyRate::factory()->create([
            'from_id' => $from->id,
            'to_id' => $to->id,
            'rate' => 2.5,
        ]);

        $this->assertSame('2.5000000000', $rate->getRawOriginal('rate'));
    }

    public function test_serialize_emits_a_decimal_string_not_a_float(): void
    {
        $user = User::factory()->create();
        $from = Currency::factory()->fromIsoCodes(['USD'])->for($user)->create();
        $to = Currency::factory()->fromIsoCodes(['EUR'])->for($user)->create();

        $rate = CurrencyRate::factory()->create([
            'from_id' => $from->id,
            'to_id' => $to->id,
            'rate' => '3.25',
        ])->fresh();

        $array = $rate->toArray();
        $this->assertIsString($array['rate']);
        $this->assertSame('3.2500000000', $array['rate']);

        $json = $rate->toJson();
        $this->assertStringContainsString('"rate":"3.2500000000"', $json);
        $this->assertStringNotContainsString('"rate":3.25', $json);
    }
}
