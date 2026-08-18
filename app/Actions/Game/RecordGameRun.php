<?php

namespace App\Actions\Game;

use App\Events\RacerFinished;
use App\Models\GameRun;
use App\Models\RaceRoom;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecordGameRun
{
    /**
     * Persist a completed game run.
     *
     * @param  array{score: int, durationMilliseconds: int, raceCode?: string|null}  $data
     */
    public function handle(User $user, array $data): GameRun
    {
        $raceRoom = $this->findStartedRaceRoom($data['raceCode'] ?? null);

        $gameRun = $user->gameRuns()->create([
            'race_room_id' => $raceRoom?->id,
            'mode' => $raceRoom === null ? GameRun::ModeSolo : GameRun::ModeRace,
            'score' => $data['score'],
            'duration_milliseconds' => $data['durationMilliseconds'],
        ]);

        if ($raceRoom !== null) {
            RacerFinished::dispatch(
                $raceRoom->code,
                $user->id,
                $user->name,
                $gameRun->score,
                $gameRun->duration_milliseconds,
            );
        }

        return $gameRun;
    }

    private function findStartedRaceRoom(?string $raceCode): ?RaceRoom
    {
        if ($raceCode === null) {
            return null;
        }

        $raceRoom = RaceRoom::query()->where('code', $raceCode)->firstOrFail();

        if ($raceRoom->starts_at === null || $raceRoom->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'raceCode' => 'This race has not started yet.',
            ]);
        }

        return $raceRoom;
    }
}
