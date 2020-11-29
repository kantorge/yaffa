<?php

use App\Currency;
use Illuminate\Database\Seeder;

class CurrencyTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run($extra)
    {
        switch($extra) {
            case 'random':
                $this->seedRandom();
                break;
            case 'fixed':
                $this->seedFixed();
                break;
            case 'sql':
                $this->seedSql();
                break;
            case 'db':
                $this->seedDb();
                break;
        }
    }

    private function seedRandom() {
        factory(Currency::class, 5)->create();
    }

    private function seedFixed() {
        Currency::create([
            'name' => 'Forint',
            'iso_code' => 'HUF',
            'num_digits' => 0,
            'suffix' => 'Ft',
            'base' => true,
            'auto_update' => false,
        ]);
        Currency::create([
            'name' => 'US Dollar',
            'iso_code' => 'USD',
            'num_digits' => 2,
            'suffix' => '$',
            'base' => null,
            'auto_update' => true,
        ]);
        Currency::create([
            'name' => 'Euro',
            'iso_code' => 'EUR',
            'num_digits' => 2,
            'suffix' => '€',
            'base' => null,
            'auto_update' => true,
        ]);
    }

    private function seedSql() {
        Eloquent::unguard();
        $path = 'storage/fin_migrations/currencies.sql';
        DB::connection('mysql')->unprepared(file_get_contents($path));
    }

    private function seedDb()
    {
        $old = DB::connection('mysql_fin_migration')->table('currencies')->get();

        foreach ($old as $item) {
            Currency::create([
                'id' => $item->id,
                'name' => $item->name,
                'iso_code' => $item->iso_code,
                'num_digits' => $item->num_digits,
                'suffix' => $item->suffix,
                'base' => $item->base,
                'auto_update' => $item->auto_update,
            ]);
       }
    }
}
