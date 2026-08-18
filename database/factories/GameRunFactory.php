<?php

namespace Database\Factories;

use App\Models\GameRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRun>
 */
class GameRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'race_room_id' => null,
            'mode' => GameRun::ModeSolo,
            'score' => fake()->numberBetween(0, 50),
            'duration_milliseconds' => fake()->numberBetween(1_000, 180_000),
        ];
    }
}
