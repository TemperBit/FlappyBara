<?php

namespace Database\Seeders;

use App\Models\GameRun;
use Illuminate\Database\Seeder;

class GameRunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GameRun::factory()->count(25)->create();
    }
}
