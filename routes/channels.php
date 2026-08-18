<?php

use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('race.{code}', function (User $user, string $code): array|false {
    $raceRoom = RaceRoom::query()
        ->where('code', $code)
        ->first(['id', 'host_id']);

    if ($raceRoom === null) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'isHost' => $raceRoom->host_id === $user->id,
    ];
});
