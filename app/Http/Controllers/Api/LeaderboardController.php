<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    public function __construct(
        private QuizService $quizService
    ) {}

    /**
     * Get the leaderboard for a game session.
     */
    public function sessionLeaderboard(int $sessionId): JsonResponse
    {
        $session = GameSession::findOrFail($sessionId);
        $leaderboard = $this->quizService->getSessionLeaderboard($session);

        return response()->json([
            'session_id' => $session->id,
            'pin' => $session->pin,
            'leaderboard' => $leaderboard,
        ]);
    }
}
