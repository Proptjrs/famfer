<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // L'administration d'abord : sans elle, aucune boutique ne peut être
        // validée, et l'espace d'arbitrage reste inatteignable.
        User::firstOrCreate(
            ['email' => 'admin@famfer.sn'],
            ['name' => 'Administration FamFer', 'password' => 'password',
             'role' => 'admin', 'telephone' => '+221 33 800 00 00']
        );

        $this->call([
            CatalogueSeeder::class,
            ClientsSeeder::class,
        ]);
    }
}
