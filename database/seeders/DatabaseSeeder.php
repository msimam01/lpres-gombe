<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Baseline CMS content: shared contact settings and the three
        // established L-PRES project components (see CmsDefaultSeeder), plus
        // the Beef cluster reference data from the original website.
        $this->call(CmsDefaultSeeder::class);
        $this->call(BeefClusterSeeder::class);

        User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
        ]);
    }
}
