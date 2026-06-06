<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create comprehensive realistic data for the platform
        $this->call([
            ComprehensiveDataSeeder::class,
            OperatorFatigueSeeder::class,
            DemoDataSeeder::class,
            RealisticPlatformSeeder::class,
        ]);
    }
}
