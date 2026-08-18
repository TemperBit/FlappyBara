<?php

use App\Actions\Game\ResolveRacePlayer;
use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('race.{code}', function (Authenticatable $player, string $code): array|false {
    $raceRoom = RaceRoom::query()
        ->where('code', $code)
        ->first(['id', 'host_id', 'host_guest_id']);

    if ($raceRoom === null) {
        return false;
    }

    $resolveRacePlayer = app(ResolveRacePlayer::class);

    return [
        'id' => $resolveRacePlayer->key($player),
        'name' => $resolveRacePlayer->name($player),
        'isHost' => Gate::forUser($player)->allows('start', $raceRoom),
    ];
}, ['guards' => ['race']]);
