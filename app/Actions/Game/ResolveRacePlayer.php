<?php

namespace App\Actions\Game;

use App\Models\User;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

class ResolveRacePlayer
{
    public const GuestIdSessionKey = 'flappybara.race_guest_id';

    public function handle(Request $request): Authenticatable
    {
        $user = $request->user('web');

        if ($user instanceof User) {
            return $user;
        }

        $guestId = $request->session()->get(self::GuestIdSessionKey);

        if (! is_string($guestId) || ! Str::isUuid($guestId)) {
            $guestId = (string) Str::uuid();
            $request->session()->put(self::GuestIdSessionKey, $guestId);
        }

        return new GenericUser([
            'id' => 'guest-'.$guestId,
            'name' => $this->guestName($guestId),
            'password' => '',
            'remember_token' => null,
        ]);
    }

    public function key(Authenticatable $player): string
    {
        if ($player instanceof User) {
            return 'user-'.$player->getKey();
        }

        return (string) $player->getAuthIdentifier();
    }

    public function name(Authenticatable $player): string
    {
        if ($player instanceof User || $player instanceof GenericUser) {
            return (string) $player->name;
        }

        throw new LogicException('Unsupported race player type.');
    }

    public function guestId(Authenticatable $player): ?string
    {
        if (! $player instanceof GenericUser) {
            return null;
        }

        $identifier = (string) $player->getAuthIdentifier();

        return Str::startsWith($identifier, 'guest-')
            ? Str::after($identifier, 'guest-')
            : null;
    }

    public function isGuest(Authenticatable $player): bool
    {
        return $player instanceof GenericUser;
    }

    public function guestName(string $guestId): string
    {
        $shortId = Str::upper(Str::substr(Str::remove('-', $guestId), 0, 4));

        return 'Guest Bara '.$shortId;
    }
}
