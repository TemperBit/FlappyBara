<?php

namespace App\Actions\Game;

use App\Events\RaceStarted;
use App\Models\RaceRoom;

class StartRace
{
    public function handle(RaceRoom $raceRoom): RaceRoom
    {
        $startsAt = now()->addSeconds(3);
        $wasStarted = RaceRoom::query()
            ->whereKey($raceRoom->getKey())
            ->whereNull('starts_at')
            ->update(['starts_at' => $startsAt]) === 1;

        $raceRoom->refresh();

        if ($wasStarted) {
            RaceStarted::dispatch(
                $raceRoom->code,
                $raceRoom->seed,
                $raceRoom->starts_at->toIso8601String(),
            );
        }

        return $raceRoom;
    }
}
