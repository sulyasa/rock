<?php

declare(strict_types=1);

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('quiz.{sessionId}', static function (User $user, int $sessionId): bool {
    // Check if the game session exists and is active
    return GameSession::where('id', $sessionId)->exists();
});

Broadcast::channel('chat.{sessionId}', static function (User $user, int $sessionId): bool {
    // Players can only join the chat channel if they are part of the session
    return GameSession::where('id', $sessionId)->exists();
});
