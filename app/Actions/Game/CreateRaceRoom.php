<?php

namespace App\Actions\Game;

use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Support\Str;

class CreateRaceRoom
{
    public function handle(User $host): RaceRoom
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (RaceRoom::query()->where('code', $code)->exists());

        return $host->hostedRaceRooms()->create([
            'code' => $code,
            'seed' => random_int(1, 2_147_483_647),
        ]);
    }
}
