<?php

namespace Database\Seeders\Fixed;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds by creating pre-defined values
     */
    public function run($aliases = [])
    {
        foreach ($aliases as $alias) {
            User::factory()->create([
                'name' => Str::ucfirst($alias) . ' User',
                'email' => $alias . '@yaffa.cc',
                'password' => Hash::make($alias),
            ]);
        }
    }
}
