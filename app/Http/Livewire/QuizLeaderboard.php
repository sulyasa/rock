<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Models\GameSession;
use App\Services\QuizService;
use Livewire\Component;

class QuizLeaderboard extends Component
{
    public int $sessionId;
    public array $leaderboard = [];

    protected $listeners = [
        'echo-private:quiz.{sessionId},.leaderboard.updated' => 'handleLeaderboardUpdate',
    ];

    public function mount(int $sessionId, QuizService $quizService): void
    {
        $this->sessionId = $sessionId;
        $this->loadLeaderboard($quizService);
    }

    /**
     * Fetch leaderboard for initial load.
     */
    public function loadLeaderboard(QuizService $quizService): void
    {
        $session = GameSession::findOrFail($this->sessionId);
        $this->leaderboard = $quizService->getSessionLeaderboard($session);
    }

    /**
     * Handle real-time leaderboard broadcast update.
     */
    public function handleLeaderboardUpdate(array $event): void
    {
        $this->leaderboard = $event['leaderboard'];
    }

    public function render()
    {
        return view('livewire.quiz-leaderboard');
    }
}
