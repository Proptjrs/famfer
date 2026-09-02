<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    /**
     * L'ordre compte : le catalogue, puis les maisons, puis leur passé.
     *
     * Chaque seeder se garde lui-même contre un second passage, de sorte qu'un
     * « db:seed » relancé n'écrase rien et ne double rien.
     */
    public function run(): void
    {
        $this->call([
            CatalogueSeeder::class,
            VendeursSeeder::class,
            HistoriqueSeeder::class,
        ]);
    }
}
