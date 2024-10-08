<?php

namespace Database\Seeders\Fixed;

use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvestmentGroupSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run(User $user): void
    {
        InvestmentGroup::factory()->create([
            'name' => 'Stock',
            'user_id' => $user->id,
        ]);
        InvestmentGroup::factory()->create([
            'name' => 'Mutual fund',
            'user_id' => $user->id,
        ]);
    }
}
