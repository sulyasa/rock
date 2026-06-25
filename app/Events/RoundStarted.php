<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $questionData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public GameSession $session,
        Question $question
    ) {
        // Strip out the is_correct flag to prevent cheating via WebSocket frames
        $this->questionData = [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'media_path' => $question->media_path,
            'media_type' => $question->media_type,
            'timer_seconds' => $question->timer_seconds,
            'options' => $question->options->map(static function ($option) {
                return [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('quiz.' . $this->session->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'round.started';
    }
}
