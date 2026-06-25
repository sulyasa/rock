<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PlayerAnswer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnswerSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $answerData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        PlayerAnswer $answer
    ) {
        $this->answerData = [
            'game_session_id' => $answer->game_session_id,
            'user_id' => $answer->user_id,
            'question_id' => $answer->question_id,
            'is_correct' => $answer->is_correct,
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
            new PrivateChannel('quiz.' . $this->answerData['game_session_id']),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'answer.submitted';
    }
}
