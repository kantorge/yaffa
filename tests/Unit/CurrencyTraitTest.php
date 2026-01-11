<?php

namespace Tests\Unit;

use App\Http\Traits\CurrencyTrait;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CurrencyTraitTest extends TestCase
{
    use RefreshDatabase;
    use CurrencyTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_get_all_currencies_returns_empty_collection_when_no_user_id(): void
    {
        $currencies = $this->getAllCurrencies(null);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $currencies);
        $this->assertCount(0, $currencies);
    }

    public function test_get_all_currencies_returns_keyed_collection(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $currency1 */
        $currency1 = Currency::factory()->for($user)->fromIsoCodes(['USD'])->create();
        /** @var Currency $currency2 */
        $currency2 = Currency::factory()->for($user)->fromIsoCodes(['EUR'])->create();

        $currencies = $this->getAllCurrencies($user->id);

        $this->assertCount(2, $currencies);

        // Should be keyed by ID
        $this->assertTrue($currencies->has($currency1->id));
        $this->assertTrue($currencies->has($currency2->id));

        // Direct access by ID
        $this->assertEquals($currency1->id, $currencies->get($currency1->id)->id);
        $this->assertEquals($currency2->id, $currencies->get($currency2->id)->id);
    }

    public function test_get_all_currencies_uses_provided_user_id(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();

        Currency::factory()->for($user1)->fromIsoCodes(['USD'])->create();
        Currency::factory()->for($user1)->fromIsoCodes(['EUR'])->create();
        Currency::factory()->for($user2)->fromIsoCodes(['USD'])->create();

        $currencies1 = $this->getAllCurrencies($user1->id);
        $currencies2 = $this->getAllCurrencies($user2->id);

        $this->assertCount(2, $currencies1);
        $this->assertCount(1, $currencies2);
    }

    public function test_get_base_currency_returns_null_when_no_user_id(): void
    {
        $baseCurrency = $this->getBaseCurrency(null);

        $this->assertNull($baseCurrency);
    }

    public function test_get_base_currency_returns_marked_base_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Currency::factory()->for($user)->fromIsoCodes(['EUR'])->create(['base' => null]);
        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);

        $result = $this->getBaseCurrency($user->id);

        $this->assertEquals($baseCurrency->id, $result->id);
        $this->assertEquals('USD', $result->iso_code);
    }

    public function test_get_base_currency_returns_first_currency_when_none_marked(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var Currency $currency1 */
        $currency1 = Currency::factory()->for($user)->create(['name' => 'EUR']);
        Currency::factory()->for($user)->create(['name' => 'USD']);

        $result = $this->getBaseCurrency($user->id);

        // Should return first by ID
        $this->assertEquals($currency1->id, $result->id);
    }

    public function test_clear_currency_cache_removes_user_cache(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Currency::factory()->for($user)->create();

        // Prime cache
        $this->getAllCurrencies($user->id);
        $cacheKey = "currencies_user_{$user->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Clear cache
        $this->clearCurrencyCache($user->id);

        // Should be gone
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_get_all_currencies_caches_for_24_hours(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Currency::factory()->for($user)->create();

        // Call to cache data
        $this->getAllCurrencies($user->id);

        $cacheKey = "currencies_user_{$user->id}";

        // Should exist now
        $this->assertTrue(Cache::has($cacheKey));

        // Travel forward 23 hours
        $this->travel(23)->hours();
        $this->assertTrue(Cache::has($cacheKey));

        // Travel forward 2 more hours (total 25 hours)
        $this->travel(2)->hours();
        $this->assertFalse(Cache::has($cacheKey));
    }
}
