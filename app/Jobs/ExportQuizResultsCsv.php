<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GameSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportQuizResultsCsv implements ShouldQueue
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
        $fileName = 'exports/quiz_' . $this->session->id . '_' . time() . '.csv';
        
        // Fetch raw data
        $rows = DB::table('player_answers')
            ->join('users', 'player_answers.user_id', '=', 'users.id')
            ->where('player_answers.game_session_id', $this->session->id)
            ->select(
                'users.id as user_id',
                'users.name',
                'users.email',
                DB::raw('SUM(player_answers.points) as total_points'),
                DB::raw('COUNT(CASE WHEN player_answers.is_correct = 1 THEN 1 END) as correct_answers')
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_points')
            ->get();

        // Create CSV structure
        $handle = fopen('php://temp', 'r+');
        
        // UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headers
        fputcsv($handle, [
            'User ID',
            'Name',
            'Email',
            'Total Points',
            'Correct Answers Count',
        ], ';');

        // Write rows
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->user_id,
                $row->name,
                $row->email,
                $row->total_points,
                $row->correct_answers,
            ], ';');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($fileName, $csvContent);

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
