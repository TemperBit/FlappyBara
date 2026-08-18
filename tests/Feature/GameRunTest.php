<?php

use App\Events\RacerFinished;
use App\Models\GameRun;
use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('guests cannot submit leaderboard runs', function () {
    $this->postJson(route('runs.store'), [
        'score' => 3,
        'durationMilliseconds' => 5_000,
    ])->assertUnauthorized();
});

test('authenticated players can submit solo runs', function () {
    $player = User::factory()->create();

    $this->actingAs($player)
        ->postJson(route('runs.store'), [
            'score' => 7,
            'durationMilliseconds' => 12_000,
        ])
        ->assertCreated()
        ->assertJsonPath('run.score', 7)
        ->assertJsonPath('run.mode', GameRun::ModeSolo)
        ->assertJsonCount(1, 'leaderboard');

    $this->assertModelExists(
        GameRun::query()->whereBelongsTo($player)->sole(),
    );
});

test('implausible scores are rejected', function () {
    $player = User::factory()->create();

    $this->actingAs($player)
        ->postJson(route('runs.store'), [
            'score' => 500,
            'durationMilliseconds' => 1_000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('score');
});

test('a run cannot be attached to a race before it starts', function () {
    $player = User::factory()->create();
    $raceRoom = RaceRoom::factory()->create();

    $this->actingAs($player)
        ->postJson(route('runs.store'), [
            'score' => 3,
            'durationMilliseconds' => 5_000,
            'raceCode' => strtolower($raceRoom->code),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('raceCode');
});

test('completed race runs are persisted and broadcast', function () {
    $player = User::factory()->create(['name' => 'Fern']);
    $raceRoom = RaceRoom::factory()->started()->create();
    Event::fake([RacerFinished::class]);

    $this->actingAs($player)
        ->postJson(route('runs.store'), [
            'score' => 8,
            'durationMilliseconds' => 15_000,
            'raceCode' => $raceRoom->code,
        ])
        ->assertCreated()
        ->assertJsonPath('run.mode', GameRun::ModeRace);

    $gameRun = GameRun::query()->whereBelongsTo($raceRoom)->sole();

    expect($gameRun)
        ->user_id->toBe($player->id)
        ->score->toBe(8);

    Event::assertDispatched(
        RacerFinished::class,
        fn (RacerFinished $event): bool => $event->raceCode === $raceRoom->code
            && $event->userId === $player->id
            && $event->score === 8,
    );
});
