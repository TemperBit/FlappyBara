<?php

namespace App\Http\Controllers;

use App\Actions\Game\GetLeaderboard;
use App\Actions\Game\RecordGameRun;
use App\Http\Requests\StoreGameRunRequest;
use Illuminate\Http\JsonResponse;

class GameRunController extends Controller
{
    public function store(
        StoreGameRunRequest $request,
        RecordGameRun $recordGameRun,
        GetLeaderboard $getLeaderboard,
    ): JsonResponse {
        $gameRun = $recordGameRun->handle($request->user(), $request->validated());

        return response()->json([
            'run' => [
                'id' => $gameRun->id,
                'score' => $gameRun->score,
                'mode' => $gameRun->mode,
            ],
            'leaderboard' => $getLeaderboard->handle(),
        ], 201);
    }
}
