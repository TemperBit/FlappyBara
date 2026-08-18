<?php

namespace App\Models;

use Database\Factories\GameRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $race_room_id
 * @property string $mode
 * @property int $score
 * @property int $duration_milliseconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read RaceRoom|null $raceRoom
 */
#[Fillable(['race_room_id', 'mode', 'score', 'duration_milliseconds'])]
class GameRun extends Model
{
    public const string ModeRace = 'race';

    public const string ModeSolo = 'solo';

    /** @use HasFactory<GameRunFactory> */
    use HasFactory;

    /**
     * Get the player who completed the run.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the race room associated with the run.
     *
     * @return BelongsTo<RaceRoom, $this>
     */
    public function raceRoom(): BelongsTo
    {
        return $this->belongsTo(RaceRoom::class);
    }
}
