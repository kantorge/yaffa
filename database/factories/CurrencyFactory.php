<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $currency = $this->faker->currencyArray();

        return [
            'name' => $currency['name'],
            'iso_code' => $currency['iso_code'],
            'base' => null,
            'auto_update' => $this->faker->boolean,
            'user_id' => User::factory(),
        ];
    }

    /**
     * CurrencyData (App\Providers\Faker\CurrencyData) is also the real registration-flow
     * currency list (RegisterController, CreateDefaultAssetsForNewUser), so it's deliberately
     * not touched here - only 4 entries, which means two independent random picks for the same
     * user collide often enough to be a recurring flaky test failure (e.g. an explicit
     * Currency::factory()->for($user)->create() call and a nested one from Investment/Account
     * factories both landing on "Euro"). Swap to an unused currency for that user before the
     * row is persisted; once all 4 real ones are taken (a test needing more than 4 distinct
     * currencies for one user), fall back to a synthetic-but-unique one rather than letting the
     * unique constraint throw.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Currency $currency) {
            if (!$currency->user_id) {
                return;
            }

            $existing = Currency::query()
                ->where('user_id', $currency->user_id)
                ->get(['name', 'iso_code']);

            $usedNames = $existing->pluck('name');
            $usedIsoCodes = $existing->pluck('iso_code');

            if (!$usedNames->contains($currency->name) && !$usedIsoCodes->contains($currency->iso_code)) {
                return;
            }

            $available = collect(CurrencyData::getCurrencies())
                ->reject(fn (array $candidate) => $usedNames->contains($candidate['name'])
                    || $usedIsoCodes->contains($candidate['iso_code']));

            if ($available->isNotEmpty()) {
                $currency->fill($available->random());

                return;
            }

            do {
                $isoCode = Str::upper(Str::random(3));
            } while ($usedIsoCodes->contains($isoCode));

            $currency->fill([
                'name' => 'Test Currency ' . $isoCode,
                'iso_code' => $isoCode,
            ]);
        });
    }

    /**
     * Create a state where the user can provide an array of ISO codes to select from,
     * assuming the selected currencies are also available in the currency faker provider.
     */
    public function fromIsoCodes(array $isoCodes): CurrencyFactory
    {
        return $this->state(function (array $attributes) use ($isoCodes) {
            $isoCode = $this->faker->randomElement($isoCodes);
            $currency = $this->faker->currencyArrayByIsoCode($isoCode);

            return [
                'name' => $currency['name'],
                'iso_code' => $currency['iso_code'],
                'base' => null,
                'auto_update' => $this->faker->boolean,
                'user_id' => $attributes['user_id'] ?? User::factory()->create()->id,
            ];
        });
    }
}
