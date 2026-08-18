<?php

namespace App\Policies;

use App\Actions\Game\ResolveRacePlayer;
use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class RaceRoomPolicy
{
    public function __construct(private readonly ResolveRacePlayer $resolveRacePlayer) {}

    public function viewAny(Authenticatable $player): bool
    {
        return true;
    }

    public function view(Authenticatable $player, RaceRoom $raceRoom): bool
    {
        return true;
    }

    public function create(Authenticatable $player): bool
    {
        return true;
    }

    public function update(Authenticatable $player, RaceRoom $raceRoom): bool
    {
        return $this->isHost($player, $raceRoom);
    }

    public function delete(Authenticatable $player, RaceRoom $raceRoom): bool
    {
        return $this->isHost($player, $raceRoom);
    }

    public function start(Authenticatable $player, RaceRoom $raceRoom): bool
    {
        return $this->isHost($player, $raceRoom);
    }

    private function isHost(Authenticatable $player, RaceRoom $raceRoom): bool
    {
        if ($player instanceof User) {
            return $player->id === $raceRoom->host_id;
        }

        $guestId = $this->resolveRacePlayer->guestId($player);

        return $guestId !== null
            && $raceRoom->host_guest_id !== null
            && hash_equals($raceRoom->host_guest_id, $guestId);
    }
}
