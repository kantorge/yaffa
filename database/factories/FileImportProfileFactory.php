<?php

namespace Database\Factories;

use App\Models\FileImportProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileImportProfile>
 */
class FileImportProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => null,
            'type' => 'user',
            'file_type' => 'csv',
            'name' => 'Custom CSV Profile ' . fake()->unique()->numberBetween(100, 999),
            'delimiter' => fake()->randomElement([',', ';']),
            'has_header_row' => true,
            'date_format' => fake()->randomElement(['Y-m-d', 'd.m.Y', 'd/m/Y']),
            'decimal_separator' => ',',
            'thousand_separator' => ' ',
            'sign_handling' => fake()->randomElement(['as_is', 'invert']),
            'mapping_json' => [
                'Date' => 'date',
                'Amount' => 'amount',
                'Payee' => 'payee',
                'Memo' => 'memo',
            ],
            'options_json' => [
                'trim_strings' => true,
                'skip_empty_rows' => true,
            ],
            'active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'key' => 'system_profile_' . fake()->unique()->numberBetween(1000, 9999),
            'type' => 'system',
        ]);
    }

    public function qif(): static
    {
        return $this->state(fn () => [
            'file_type' => 'qif',
            'name' => 'Custom QIF Profile ' . fake()->unique()->numberBetween(100, 999),
            'delimiter' => null,
            'has_header_row' => false,
            'date_format' => null,
            'mapping_json' => null,
            'options_json' => [
                'field_map' => [
                    'payee' => 'P',
                    'comment' => 'M',
                ],
                'amount_sign' => 'normal',
            ],
        ]);
    }
}
