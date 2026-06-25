<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $messageData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        ChatMessage $message
    ) {
        $this->messageData = [
            'id' => $message->id,
            'game_session_id' => $message->game_session_id,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name,
            'message_text' => $message->message_text,
            'recipient_id' => $message->recipient_id,
            'is_system' => $message->is_system,
            'created_at' => $message->created_at->toIso8601String(),
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
            new PrivateChannel('chat.' . $this->messageData['game_session_id']),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }
}
