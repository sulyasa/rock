<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\QuizResultsExport;
use App\Models\GameSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class ExportQuizResultsExcel implements ShouldQueue
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
        $fileName = 'exports/quiz_' . $this->session->id . '_' . time() . '.xlsx';
        
        Excel::store(new QuizResultsExport($this->session), $fileName, 'public');

        // Store file path in cache for the user to download
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
