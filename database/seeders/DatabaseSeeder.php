<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);

        // Skip demo data in production unless explicitly enabled via env.
        if (! app()->isProduction() || env('SEED_DEMO_DATA') === true) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
