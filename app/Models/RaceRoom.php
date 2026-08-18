<?php

namespace App\Models;

use Database\Factories\RaceRoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $host_id
 * @property string|null $host_guest_id
 * @property string $code
 * @property int $seed
 * @property Carbon|null $starts_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $host
 * @property-read Collection<int, GameRun> $gameRuns
 */
#[Fillable(['host_id', 'host_guest_id', 'code', 'seed', 'starts_at'])]
class RaceRoom extends Model
{
    /** @use HasFactory<RaceRoomFactory> */
    use HasFactory;

    /**
     * Get the player who created the room.
     *
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * Get the runs completed in this room.
     *
     * @return HasMany<GameRun, $this>
     */
    public function gameRuns(): HasMany
    {
        return $this->hasMany(GameRun::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }
}
