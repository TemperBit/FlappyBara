<?php

namespace Database\Seeders;

use App\Models\RaceRoom;
use Illuminate\Database\Seeder;

class RaceRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RaceRoom::factory()->count(3)->create();
    }
}
