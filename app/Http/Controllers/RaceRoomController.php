<?php

namespace App\Http\Controllers;

use App\Actions\Game\CreateRaceRoom;
use App\Actions\Game\GetLeaderboard;
use App\Actions\Game\ResolveRacePlayer;
use App\Actions\Game\StartRace;
use App\Models\RaceRoom;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RaceRoomController extends Controller
{
    public function __construct(private readonly ResolveRacePlayer $resolveRacePlayer) {}

    public function store(Request $request, CreateRaceRoom $createRaceRoom): RedirectResponse
    {
        $player = $this->resolveRacePlayer->handle($request);
        Gate::forUser($player)->authorize('create', RaceRoom::class);

        return to_route('races.show', $createRaceRoom->handle($player));
    }

    public function show(
        Request $request,
        RaceRoom $raceRoom,
        GetLeaderboard $getLeaderboard,
    ): Response {
        $player = $this->resolveRacePlayer->handle($request);
        Gate::forUser($player)->authorize('view', $raceRoom);

        return Inertia::render('Game', [
            'leaderboard' => $getLeaderboard->handle(),
            'race' => $this->raceData($raceRoom, $player),
        ]);
    }

    public function start(
        Request $request,
        RaceRoom $raceRoom,
        StartRace $startRace,
    ): JsonResponse {
        $player = $this->resolveRacePlayer->handle($request);
        Gate::forUser($player)->authorize('start', $raceRoom);
        $raceRoom = $startRace->handle($raceRoom);

        return response()->json([
            'race' => $this->raceData($raceRoom, $player),
        ]);
    }

    /**
     * @return array{code: string, seed: int, startsAt: string|null, isHost: bool, hostName: string, player: array{id: string, name: string, isGuest: bool}}
     */
    private function raceData(RaceRoom $raceRoom, Authenticatable $player): array
    {
        $raceRoom->loadMissing('host:id,name');

        return [
            'code' => $raceRoom->code,
            'seed' => $raceRoom->seed,
            'startsAt' => $raceRoom->starts_at?->toIso8601String(),
            'isHost' => Gate::forUser($player)->allows('start', $raceRoom),
            'hostName' => $raceRoom->host?->name
                ?? $this->resolveRacePlayer->guestName((string) $raceRoom->host_guest_id),
            'player' => [
                'id' => $this->resolveRacePlayer->key($player),
                'name' => $this->resolveRacePlayer->name($player),
                'isGuest' => $this->resolveRacePlayer->isGuest($player),
            ],
        ];
    }
}
