<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Models\GameSession;
use App\Models\Question;
use App\Services\QuizService;
use Livewire\Component;

class QuizGame extends Component
{
    public int $sessionId;
    public ?int $selectedOptionId = null;
    public bool $hasAnswered = false;
    public bool $showFeedback = false;
    public array $questionStats = [];

    protected $listeners = [
        'echo-private:quiz.{sessionId},.round.started' => 'handleRoundStarted',
        'echo-private:quiz.{sessionId},.quiz.finished' => 'handleQuizFinished',
    ];

    public function mount(int $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Submit selected option.
     */
    public function selectOption(int $optionId, QuizService $quizService): void
    {
        if ($this->hasAnswered) {
            return;
        }

        $session = GameSession::findOrFail($this->sessionId);

        try {
            $answer = $quizService->submitPlayerAnswer($session, auth()->user(), $optionId);
            $this->selectedOptionId = $optionId;
            $this->hasAnswered = true;
            $this->showFeedback = true;

            // Emit local event to trigger browser animations or audio
            $this->emit('answerSubmitted', [
                'is_correct' => $answer->is_correct,
                'points' => $answer->points,
            ]);
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Listener for new round started (broadcast from host).
     */
    public function handleRoundStarted(array $event): void
    {
        $this->hasAnswered = false;
        $this->selectedOptionId = null;
        $this->showFeedback = false;
        $this->questionStats = [];

        $this->emit('newRoundStarted', $event['questionData']);
    }

    /**
     * Listener for game finished.
     */
    public function handleQuizFinished(): void
    {
        $this->emit('gameFinished');
    }

    /**
     * Fetch answers distribution to show stats on round end.
     */
    public function showRoundStatistics(int $questionId): void
    {
        $question = Question::findOrFail($questionId);
        
        $stats = $question->options()
            ->leftJoin('player_answers', 'answer_options.id', '=', 'player_answers.answer_option_id')
            ->where('player_answers.game_session_id', $this->sessionId)
            ->select('answer_options.option_text', \DB::raw('count(player_answers.id) as count'))
            ->groupBy('answer_options.id', 'answer_options.option_text')
            ->get()
            ->toArray();

        $this->questionStats = $stats;
        $this->emit('renderStatsChart', $stats);
    }

    public function render()
    {
        $session = GameSession::with(['currentQuestion.options'])->findOrFail($this->sessionId);

        return view('livewire.quiz-game', [
            'session' => $session,
        ]);
    }
}
