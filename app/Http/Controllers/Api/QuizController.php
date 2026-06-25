<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAnswerRequest;
use App\Models\GameSession;
use App\Models\Quiz;
use App\Services\QuizService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(
        private QuizService $quizService
    ) {}

    /**
     * Display a list of active quizzes.
     */
    public function index(): JsonResponse
    {
        $quizzes = Quiz::where('status', 'active')
            ->select('id', 'title', 'description', 'rounds_count')
            ->withCount('questions')
            ->get();

        return response()->json($quizzes);
    }

    /**
     * Show quiz details.
     */
    public function show(int $id): JsonResponse
    {
        $quiz = Quiz::where('status', 'active')
            ->with(['questions' => function ($query) {
                $query->select('id', 'quiz_id', 'question_text', 'media_path', 'media_type', 'timer_seconds', 'order')
                      ->with(['options' => function ($optQuery) {
                          // Crucial: hide is_correct to prevent cheating
                          $optQuery->select('id', 'question_id', 'option_text');
                      }]);
            }])
            ->findOrFail($id);

        return response()->json($quiz);
    }

    /**
     * Join an active game session using a PIN.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'size:6'],
        ]);

        $session = GameSession::where('pin', $request->input('pin'))
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Сессия с указанным PIN-кодом не найдена или уже завершена.',
            ], 404);
        }

        return response()->json([
            'message' => 'Успешное подключение к сессии.',
            'session' => [
                'id' => $session->id,
                'pin' => $session->pin,
                'status' => $session->status,
                'quiz' => [
                    'id' => $session->quiz->id,
                    'title' => $session->quiz->title,
                ],
            ],
        ]);
    }

    /**
     * Submit an answer for the current question in a game session.
     */
    public function submitAnswer(SubmitAnswerRequest $request, int $sessionId): JsonResponse
    {
        $session = GameSession::findOrFail($sessionId);
        $user = $request->user();

        try {
            $answer = $this->quizService->submitPlayerAnswer(
                $session,
                $user,
                (int) $request->input('option_id')
            );

            return response()->json([
                'message' => 'Ответ успешно принят.',
                'points_awarded' => $answer->points,
                'is_correct' => $answer->is_correct,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
