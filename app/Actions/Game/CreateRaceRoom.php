<?php

namespace App\Actions\Game;

use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;

class CreateRaceRoom
{
    public function __construct(private readonly ResolveRacePlayer $resolveRacePlayer) {}

    public function handle(Authenticatable $host): RaceRoom
    {
        do {
            $code = RaceRoom::generateCode();
        } while (RaceRoom::query()->where('code', $code)->exists());

        $attributes = [
            'code' => $code,
            'seed' => random_int(1, 2_147_483_647),
        ];

        if ($host instanceof User) {
            $attributes['host_id'] = $host->id;
        } else {
            $attributes['host_guest_id'] = $this->resolveRacePlayer->guestId($host)
                ?? throw new LogicException('Guest race player is missing an identifier.');
        }

        return RaceRoom::query()->create($attributes);
    }
}
