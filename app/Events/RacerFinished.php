<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RacerFinished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $raceCode,
        public readonly string $playerId,
        public readonly string $playerName,
        public readonly int $score,
        public readonly int $durationMilliseconds,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('race.'.$this->raceCode),
        ];
    }

    public function broadcastAs(): string
    {
        return 'race.finished';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{playerId: string, playerName: string, score: int, durationMilliseconds: int}
     */
    public function broadcastWith(): array
    {
        return [
            'playerId' => $this->playerId,
            'playerName' => $this->playerName,
            'score' => $this->score,
            'durationMilliseconds' => $this->durationMilliseconds,
        ];
    }
}
