<?php

use App\Events\RaceStarted;
use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to login before creating a race room', function () {
    $this->post(route('races.store'))->assertRedirect(route('login'));
});

test('authenticated players can create an invite room', function () {
    $host = User::factory()->create();

    $response = $this->actingAs($host)->post(route('races.store'));
    $raceRoom = RaceRoom::query()->sole();

    $response->assertRedirect(route('races.show', $raceRoom));
    expect($raceRoom)
        ->host_id->toBe($host->id)
        ->code->toHaveLength(6)
        ->starts_at->toBeNull();
});

test('signed in players can join a race by invite code', function () {
    $this->withoutVite();

    $host = User::factory()->create(['name' => 'Host Bara']);
    $guest = User::factory()->create();
    $raceRoom = RaceRoom::factory()->for($host, 'host')->create();

    $this->actingAs($guest)
        ->get(route('races.show', $raceRoom))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Game')
            ->where('race.code', $raceRoom->code)
            ->where('race.hostName', 'Host Bara')
            ->where('race.isHost', false));
});

test('only the host can start a race', function () {
    $host = User::factory()->create();
    $guest = User::factory()->create();
    $raceRoom = RaceRoom::factory()->for($host, 'host')->create();

    $this->actingAs($guest)
        ->postJson(route('races.start', $raceRoom))
        ->assertForbidden();

    Event::fake([RaceStarted::class]);

    $this->actingAs($host)
        ->postJson(route('races.start', $raceRoom))
        ->assertOk()
        ->assertJsonPath('race.code', $raceRoom->code);

    expect($raceRoom->refresh()->starts_at)->not->toBeNull();

    Event::assertDispatched(
        RaceStarted::class,
        fn (RaceStarted $event): bool => $event->raceCode === $raceRoom->code
            && $event->seed === $raceRoom->seed,
    );
});
