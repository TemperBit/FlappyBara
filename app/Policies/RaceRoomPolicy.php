<?php

namespace App\Policies;

use App\Models\RaceRoom;
use App\Models\User;

class RaceRoomPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RaceRoom $raceRoom): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RaceRoom $raceRoom): bool
    {
        return $user->id === $raceRoom->host_id;
    }

    public function delete(User $user, RaceRoom $raceRoom): bool
    {
        return $user->id === $raceRoom->host_id;
    }

    public function start(User $user, RaceRoom $raceRoom): bool
    {
        return $user->id === $raceRoom->host_id;
    }
}
