<?php

namespace App\Http\Controllers;

use App\Actions\Game\GetLeaderboard;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function __invoke(GetLeaderboard $getLeaderboard): Response
    {
        return Inertia::render('Game', [
            'leaderboard' => $getLeaderboard->handle(),
        ]);
    }
}
