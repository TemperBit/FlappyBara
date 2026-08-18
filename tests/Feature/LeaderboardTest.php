<?php

use App\Models\GameRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the global leaderboard shows the highest scoring runs first', function () {
    $this->withoutVite();

    $river = User::factory()->create(['name' => 'River']);
    $willow = User::factory()->create(['name' => 'Willow']);

    GameRun::factory()->for($river)->create([
        'score' => 12,
        'duration_milliseconds' => 42_000,
    ]);
    GameRun::factory()->for($willow)->create([
        'score' => 19,
        'duration_milliseconds' => 64_000,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Game')
            ->has('leaderboard', 2)
            ->where('leaderboard.0.rank', 1)
            ->where('leaderboard.0.player', 'Willow')
            ->where('leaderboard.0.score', 19)
            ->where('leaderboard.1.player', 'River'));
});
