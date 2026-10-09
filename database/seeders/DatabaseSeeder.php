<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Tablet', 'Kapsul', 'Botol', 'Tube', 'Ampul', 'Sachet'] as $name) {
            Unit::firstOrCreate(['name' => $name]);
        }
        Location::firstOrCreate(['code' => 'GDG'], ['name' => 'Gudang']);
        Location::firstOrCreate(['code' => 'APT'], ['name' => 'Apotek']);
    }
}
