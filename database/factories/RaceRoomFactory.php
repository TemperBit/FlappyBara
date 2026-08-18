<?php

namespace Database\Factories;

use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RaceRoom>
 */
class RaceRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host_id' => User::factory(),
            'code' => fake()->unique()->regexify('[A-HJ-NP-Z2-9]{6}'),
            'seed' => fake()->numberBetween(1, 2_147_483_647),
            'starts_at' => null,
        ];
    }

    /**
     * Indicate that the race has started.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subSecond(),
        ]);
    }

    /**
     * Indicate that a guest player hosts the race.
     */
    public function guestHosted(?string $guestId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'host_id' => null,
            'host_guest_id' => $guestId ?? (string) Str::uuid(),
        ]);
    }
}
