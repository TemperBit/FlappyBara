<?php

use App\Actions\Game\ResolveRacePlayer;
use App\Events\RacerFinished;
use App\Events\RaceStarted;
use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);

    require base_path('routes/channels.php');
});

test('a guest can authorize the race presence channel', function () {
    $guestId = (string) Str::uuid();
    $raceRoom = RaceRoom::factory()->guestHosted($guestId)->create();

    $response = $this
        ->withSession([ResolveRacePlayer::GuestIdSessionKey => $guestId])
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-race.'.$raceRoom->code,
        ])
        ->assertOk();

    $channelData = json_decode(
        $response->json('channel_data'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($channelData)->toMatchArray([
        'user_id' => 'guest-'.$guestId,
        'user_info' => [
            'id' => 'guest-'.$guestId,
            'name' => resolve(ResolveRacePlayer::class)->guestName($guestId),
            'isHost' => true,
        ],
    ]);
});

test('an authenticated player can authorize the race presence channel', function () {
    $host = User::factory()->create();
    $player = User::factory()->create(['name' => 'Willow']);
    $raceRoom = RaceRoom::factory()->for($host, 'host')->create();

    $response = $this
        ->actingAs($player)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-race.'.$raceRoom->code,
        ])
        ->assertOk();

    $channelData = json_decode(
        $response->json('channel_data'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($channelData)->toMatchArray([
        'user_id' => (string) $player->id,
        'user_info' => [
            'id' => 'user-'.$player->id,
            'name' => 'Willow',
            'isHost' => false,
        ],
    ]);
});

test('a missing race cannot authorize a presence channel', function () {
    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'presence-race.ABC234',
    ])->assertForbidden();
});

test('starting a race twice only broadcasts its original start once', function () {
    $host = User::factory()->create();
    $raceRoom = RaceRoom::factory()->for($host, 'host')->create();

    Event::fake([RaceStarted::class]);

    $this->actingAs($host)
        ->postJson(route('races.start', $raceRoom))
        ->assertOk();

    $originalStart = $raceRoom->refresh()->starts_at;

    $this->travel(10)->seconds();

    $this->actingAs($host)
        ->postJson(route('races.start', $raceRoom))
        ->assertOk();

    expect($raceRoom->refresh()->starts_at->equalTo($originalStart))->toBeTrue();
    Event::assertDispatchedTimes(RaceStarted::class, 1);
});

test('race started broadcasts its explicit presence payload', function () {
    $event = new RaceStarted('ABC234', 42, '2026-08-18T12:00:03+00:00');
    $channel = $event->broadcastOn()[0];

    expect($event)
        ->toBeInstanceOf(ShouldBroadcastNow::class)
        ->broadcastAs()->toBe('race.started')
        ->broadcastWith()->toBe([
            'code' => 'ABC234',
            'seed' => 42,
            'startsAt' => '2026-08-18T12:00:03+00:00',
        ])
        ->and($channel)
        ->toBeInstanceOf(PresenceChannel::class)
        ->name->toBe('presence-race.ABC234');
});

test('racer finished broadcasts its explicit presence payload', function () {
    $event = new RacerFinished('ABC234', 'user-7', 'Fern', 12, 45_000);
    $channel = $event->broadcastOn()[0];

    expect($event)
        ->toBeInstanceOf(ShouldBroadcastNow::class)
        ->broadcastAs()->toBe('race.finished')
        ->broadcastWith()->toBe([
            'playerId' => 'user-7',
            'playerName' => 'Fern',
            'score' => 12,
            'durationMilliseconds' => 45_000,
        ])
        ->and($channel)
        ->toBeInstanceOf(PresenceChannel::class)
        ->name->toBe('presence-race.ABC234');
});
