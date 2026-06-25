<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GameSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportQuizResultsPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private GameSession $session,
        private string $exportKey
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $session = $this->session;

        // Fetch final scores
        $leaderboard = DB::table('player_answers')
            ->join('users', 'player_answers.user_id', '=', 'users.id')
            ->where('player_answers.game_session_id', $session->id)
            ->select(
                'users.name',
                DB::raw('SUM(player_answers.points) as total_points'),
                DB::raw('COUNT(CASE WHEN player_answers.is_correct = 1 THEN 1 END) as correct_answers')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_points')
            ->get();

        // Fetch questions stats
        $questionsStats = DB::table('questions')
            ->leftJoin('player_answers', function ($join) use ($session) {
                $join->on('questions.id', '=', 'player_answers.question_id')
                     ->where('player_answers.game_session_id', '=', $session->id);
            })
            ->where('questions.quiz_id', $session->quiz_id)
            ->select(
                'questions.question_text',
                'questions.order',
                DB::raw('COUNT(player_answers.id) as total_responses'),
                DB::raw('COUNT(CASE WHEN player_answers.is_correct = 1 THEN 1 END) as correct_responses')
            )
            ->groupBy('questions.id', 'questions.question_text', 'questions.order')
            ->orderBy('questions.order')
            ->get();

        // Render PDF view
        $pdf = Pdf::loadView('exports.results_pdf', [
            'session' => $session,
            'leaderboard' => $leaderboard,
            'questionsStats' => $questionsStats,
        ]);

        $fileName = 'exports/quiz_' . $session->id . '_' . time() . '.pdf';
        Storage::disk('public')->put($fileName, $pdf->output());

        Cache::put("export_file_{$this->exportKey}", [
            'status' => 'completed',
            'url' => '/storage/' . $fileName,
        ], now()->addHours(2));
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Cache::put("export_file_{$this->exportKey}", [
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ], now()->addHours(2));
    }
}
