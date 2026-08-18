<?php

use App\Actions\Game\ResolveRacePlayer;
use App\Events\RaceStarted;
use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can create an invite room without an account', function () {
    $this->withoutVite();

    $response = $this->post(route('races.store'));
    $raceRoom = RaceRoom::query()->sole();

    $response->assertRedirect(route('races.show', $raceRoom));
    expect($raceRoom)
        ->host_id->toBeNull()
        ->host_guest_id->not->toBeNull()
        ->code->toHaveLength(6);

    $this->get(route('races.show', $raceRoom))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('race.isHost', true)
            ->where('race.player.isGuest', true));
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

test('a guest host can start their race but another guest cannot', function () {
    $hostGuestId = (string) Str::uuid();
    $otherGuestId = (string) Str::uuid();
    $raceRoom = RaceRoom::factory()->guestHosted($hostGuestId)->create();

    $this->withSession([ResolveRacePlayer::GuestIdSessionKey => $otherGuestId])
        ->postJson(route('races.start', $raceRoom))
        ->assertForbidden();

    Event::fake([RaceStarted::class]);

    $this->withSession([ResolveRacePlayer::GuestIdSessionKey => $hostGuestId])
        ->postJson(route('races.start', $raceRoom))
        ->assertOk()
        ->assertJsonPath('race.player.id', 'guest-'.$hostGuestId);

    Event::assertDispatched(RaceStarted::class);
});

test('the race guard resolves a session-backed guest player', function () {
    $guestId = (string) Str::uuid();
    $request = Request::create('/broadcasting/auth', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put(ResolveRacePlayer::GuestIdSessionKey, $guestId);

    $guard = Auth::guard('race');
    $guard->setRequest($request);
    $player = $guard->user();

    expect($player)
        ->toBeInstanceOf(GenericUser::class)
        ->getAuthIdentifier()->toBe('guest-'.$guestId)
        ->name->toStartWith('Guest Bara ');
});
