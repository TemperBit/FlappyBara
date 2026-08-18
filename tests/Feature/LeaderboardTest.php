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

test('leaderboard ties prefer shorter and then newer runs', function () {
    $this->withoutVite();

    $slow = User::factory()->create(['name' => 'Slow Bara']);
    $olderFast = User::factory()->create(['name' => 'Older Fast Bara']);
    $newerFast = User::factory()->create(['name' => 'Newer Fast Bara']);

    GameRun::factory()->for($slow)->create([
        'score' => 20,
        'duration_milliseconds' => 30_000,
    ]);
    GameRun::factory()->for($olderFast)->create([
        'score' => 20,
        'duration_milliseconds' => 20_000,
    ]);
    GameRun::factory()->for($newerFast)->create([
        'score' => 20,
        'duration_milliseconds' => 20_000,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaderboard.0.player', 'Newer Fast Bara')
            ->where('leaderboard.1.player', 'Older Fast Bara')
            ->where('leaderboard.2.player', 'Slow Bara'));
});
