<?php

namespace App\Http\Controllers;

use App\Actions\Game\CreateRaceRoom;
use App\Actions\Game\GetLeaderboard;
use App\Actions\Game\StartRace;
use App\Models\RaceRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RaceRoomController extends Controller
{
    public function store(Request $request, CreateRaceRoom $createRaceRoom): RedirectResponse
    {
        Gate::authorize('create', RaceRoom::class);

        return to_route('races.show', $createRaceRoom->handle($request->user()));
    }

    public function show(
        Request $request,
        RaceRoom $raceRoom,
        GetLeaderboard $getLeaderboard,
    ): Response {
        Gate::authorize('view', $raceRoom);

        return Inertia::render('Game', [
            'leaderboard' => $getLeaderboard->handle(),
            'race' => $this->raceData($raceRoom, $request),
        ]);
    }

    public function start(
        Request $request,
        RaceRoom $raceRoom,
        StartRace $startRace,
    ): JsonResponse {
        Gate::authorize('start', $raceRoom);
        $raceRoom = $startRace->handle($raceRoom);

        return response()->json([
            'race' => $this->raceData($raceRoom, $request),
        ]);
    }

    /**
     * @return array{code: string, seed: int, startsAt: string|null, isHost: bool, hostName: string}
     */
    private function raceData(RaceRoom $raceRoom, Request $request): array
    {
        $raceRoom->loadMissing('host:id,name');

        return [
            'code' => $raceRoom->code,
            'seed' => $raceRoom->seed,
            'startsAt' => $raceRoom->starts_at?->toIso8601String(),
            'isHost' => $request->user()->is($raceRoom->host),
            'hostName' => $raceRoom->host->name,
        ];
    }
}
