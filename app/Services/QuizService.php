<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AnswerSubmitted;
use App\Events\LeaderboardUpdated;
use App\Events\QuizStarted;
use App\Events\RoundStarted;
use App\Models\AnswerOption;
use App\Models\GameSession;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuizService
{
    public function __construct(
        private ScoreCalculator $scoreCalculator
    ) {}

    /**
     * Start a new live game session for a quiz.
     */
    public function startSession(Quiz $quiz): GameSession
    {
        // Generate unique 6-digit PIN
        do {
            $pin = (string) random_int(100000, 999999);
        } while (GameSession::where('pin', $pin)->where('status', 'active')->exists());

        $session = GameSession::create([
            'quiz_id' => $quiz->id,
            'pin' => $pin,
            'status' => 'active',
            'current_question_id' => null,
            'current_question_started_at' => null,
        ]);

        broadcast(new QuizStarted($session))->toOthers();

        return $session;
    }

    /**
     * Move game session to the specified question.
     */
    public function nextQuestion(GameSession $session, Question $question): void
    {
        if ($session->status !== 'active') {
            throw new RuntimeException("Cannot advance question on an inactive game session.");
        }

        $session->update([
            'current_question_id' => $question->id,
            'current_question_started_at' => now(),
        ]);

        // Clear cached leaderboard for this round
        Cache::forget("session_leaderboard_{$session->id}");

        broadcast(new RoundStarted($session, $question))->toOthers();
    }

    /**
     * Submit player's answer for the current question.
     */
    public function submitPlayerAnswer(GameSession $session, User $user, int $optionId): PlayerAnswer
    {
        if ($session->status !== 'active') {
            throw new RuntimeException("Game session is not active.");
        }

        $currentQuestionId = $session->current_question_id;
        if (!$currentQuestionId) {
            throw new RuntimeException("No active question in this session.");
        }

        // Check if player already answered this question in this session
        $existing = PlayerAnswer::where('game_session_id', $session->id)
            ->where('user_id', $user->id)
            ->where('question_id', $currentQuestionId)
            ->first();

        if ($existing) {
            throw new RuntimeException("Player has already answered this question.");
        }

        $option = AnswerOption::where('question_id', $currentQuestionId)
            ->where('id', $optionId)
            ->firstOrFail();

        $startedAt = $session->current_question_started_at;
        $responseTimeMs = $startedAt ? (int) (now()->diffInMilliseconds($startedAt)) : 0;
        
        // Enforce time limit blocking
        $timerMs = $option->question->timer_seconds * 1000;
        if ($responseTimeMs > $timerMs) {
            throw new RuntimeException("Time limit exceeded for this question.");
        }

        return DB::transaction(function () use ($session, $user, $currentQuestionId, $option, $responseTimeMs) {
            // Check if this is the first correct answer for speed bonus
            $isCorrect = $option->is_correct;
            $isFastest = false;

            if ($isCorrect) {
                $hasCorrectAnswers = PlayerAnswer::where('game_session_id', $session->id)
                    ->where('question_id', $currentQuestionId)
                    ->where('is_correct', true)
                    ->lockForUpdate()
                    ->exists();

                $isFastest = !$hasCorrectAnswers;
            }

            $points = $this->scoreCalculator->calculatePoints(
                $isCorrect,
                $option->question->timer_seconds,
                $responseTimeMs,
                $isFastest
            );

            $answer = PlayerAnswer::create([
                'game_session_id' => $session->id,
                'user_id' => $user->id,
                'question_id' => $currentQuestionId,
                'answer_option_id' => $option->id,
                'response_time_ms' => $responseTimeMs,
                'points' => $points,
                'is_correct' => $isCorrect,
                'is_fastest' => $isFastest,
            ]);

            // Clear leaderboard cache to trigger fresh calculation
            Cache::forget("session_leaderboard_{$session->id}");

            // Broadcast events
            broadcast(new AnswerSubmitted($answer))->toOthers();
            
            // Broadcast dynamic leaderboard update
            $leaderboard = $this->getSessionLeaderboard($session);
            broadcast(new LeaderboardUpdated($session, $leaderboard))->toOthers();

            return $answer;
        });
    }

    /**
     * Get the leaderboard for a game session (cached).
     *
     * @return array<int, array{name: string, score: int, correct_answers: int}>
     */
    public function getSessionLeaderboard(GameSession $session): array
    {
        return Cache::remember("session_leaderboard_{$session->id}", 60, function () use ($session) {
            return DB::table('player_answers')
                ->join('users', 'player_answers.user_id', '=', 'users.id')
                ->where('player_answers.game_session_id', $session->id)
                ->select(
                    'users.id as user_id',
                    'users.name',
                    DB::raw('SUM(player_answers.points) as score'),
                    DB::raw('COUNT(CASE WHEN player_answers.is_correct = 1 THEN 1 END) as correct_answers')
                )
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('score')
                ->limit(10)
                ->get()
                ->toArray();
        });
    }

    /**
     * Close game session.
     */
    public function finishSession(GameSession $session): void
    {
        $session->update(['status' => 'finished']);
        Cache::forget("session_leaderboard_{$session->id}");
    }
}
