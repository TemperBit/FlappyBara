<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RaceStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $raceCode,
        public readonly int $seed,
        public readonly string $startsAt,
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
        return 'race.started';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array{code: string, seed: int, startsAt: string}
     */
    public function broadcastWith(): array
    {
        return [
            'code' => $this->raceCode,
            'seed' => $this->seed,
            'startsAt' => $this->startsAt,
        ];
    }
}
