<?php

namespace App\Actions\Game;

use App\Models\GameRun;

class GetLeaderboard
{
    /**
     * Get the highest-scoring runs for the global leaderboard.
     *
     * @return array<int, array{rank: int, player: string, score: int, mode: string, durationMilliseconds: int, playedAt: string}>
     */
    public function handle(int $limit = 10): array
    {
        return GameRun::query()
            ->select(['id', 'user_id', 'score', 'mode', 'duration_milliseconds', 'created_at'])
            ->with('user:id,name')
            ->orderByDesc('score')
            ->orderBy('duration_milliseconds')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->values()
            ->map(fn (GameRun $run, int $index): array => [
                'rank' => $index + 1,
                'player' => $run->user->name,
                'score' => $run->score,
                'mode' => $run->mode,
                'durationMilliseconds' => $run->duration_milliseconds,
                'playedAt' => $run->created_at->toIso8601String(),
            ])
            ->all();
    }
}
