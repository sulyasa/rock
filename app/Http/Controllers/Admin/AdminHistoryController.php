<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\UserActionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminHistoryController extends Controller
{
    /**
     * Get list of completed game sessions.
     */
    public function gameHistory(): JsonResponse
    {
        $history = GameSession::where('status', 'finished')
            ->with(['quiz:id,title,rounds_count'])
            ->withCount(['playerAnswers as total_participants' => static function ($query) {
                $query->select(DB::raw('count(distinct(user_id))'));
            }])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($history);
    }

    /**
     * Get detailed stats of a completed session.
     */
    public function sessionDetails(int $sessionId): JsonResponse
    {
        $session = GameSession::where('status', 'finished')->findOrFail($sessionId);

        // Get final scores of all players
        $scores = DB::table('player_answers')
            ->join('users', 'player_answers.user_id', '=', 'users.id')
            ->where('player_answers.game_session_id', $session->id)
            ->select(
                'users.id as user_id',
                'users.name',
                DB::raw('SUM(player_answers.points) as total_score'),
                DB::raw('COUNT(CASE WHEN player_answers.is_correct = 1 THEN 1 END) as correct_answers'),
                DB::raw('COUNT(player_answers.id) as total_answers_count')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_score')
            ->get();

        // Get statistics per question
        $questionsStats = DB::table('questions')
            ->leftJoin('player_answers', function ($join) use ($session) {
                $join->on('questions.id', '=', 'player_answers.question_id')
                     ->where('player_answers.game_session_id', '=', $session->id);
            })
            ->where('questions.quiz_id', $session->quiz_id)
            ->select(
                'questions.id as question_id',
                'questions.question_text',
                'questions.order',
                DB::raw('COUNT(player_answers.id) as total_responses'),
                DB::raw('COUNT(CASE WHEN player_answers.is_correct = 1 THEN 1 END) as correct_responses'),
                DB::raw('AVG(player_answers.response_time_ms) as avg_response_time_ms')
            )
            ->groupBy('questions.id', 'questions.question_text', 'questions.order')
            ->orderBy('questions.order')
            ->get();

        return response()->json([
            'session' => [
                'id' => $session->id,
                'pin' => $session->pin,
                'finished_at' => $session->updated_at,
                'quiz' => $session->quiz->only(['id', 'title']),
            ],
            'leaderboard' => $scores,
            'questions_statistics' => $questionsStats,
        ]);
    }

    /**
     * View audit logs for security and tracking.
     */
    public function userLogs(): JsonResponse
    {
        $logs = UserActionLog::with('user:id,name,email,role')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }
}
