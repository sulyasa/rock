<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\GameSession;
use App\Services\BadWordFilter;
use Livewire\Component;

class QuizChat extends Component
{
    public int $sessionId;
    public string $messageText = '';
    public ?int $recipientId = null; // null means public room, user_id means private
    public array $messages = [];

    protected $listeners = [
        'echo-private:chat.{sessionId},.chat.message.sent' => 'handleIncomingMessage',
    ];

    public function mount(int $sessionId): void
    {
        $this->sessionId = $sessionId;
        $this->loadMessages();
    }

    /**
     * Load initial chat messages.
     */
    public function loadMessages(): void
    {
        $session = GameSession::findOrFail($this->sessionId);
        $userId = auth()->id();

        $this->messages = ChatMessage::with('user:id,name')
            ->where('game_session_id', $session->id)
            ->where(function ($query) use ($userId) {
                // Public messages or private messages where current user is sender or recipient
                $query->whereNull('recipient_id')
                      ->orWhere('user_id', $userId)
                      ->orWhere('recipient_id', $userId);
            })
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * Handle incoming real-time broadcast message.
     */
    public function handleIncomingMessage(array $event): void
    {
        $userId = auth()->id();
        $messageData = $event['messageData'];

        // Only append if it's a public message, or the current user is sender or recipient
        if (
            is_null($messageData['recipient_id']) || 
            $messageData['user_id'] === $userId || 
            $messageData['recipient_id'] === $userId
        ) {
            $this->messages[] = [
                'id' => $messageData['id'],
                'user_id' => $messageData['user_id'],
                'user' => ['name' => $messageData['user_name']],
                'message_text' => $messageData['message_text'],
                'recipient_id' => $messageData['recipient_id'],
                'is_system' => $messageData['is_system'],
                'created_at' => $messageData['created_at'],
            ];
            
            $this->emit('scrollChatToBottom');
        }
    }

    /**
     * Send chat message.
     */
    public function sendMessage(BadWordFilter $wordFilter): void
    {
        $this->validate([
            'messageText' => ['required', 'string', 'max:500'],
        ]);

        // Moderate message content using the filter service
        $cleanText = $wordFilter->clean($this->messageText);

        $message = ChatMessage::create([
            'game_session_id' => $this->sessionId,
            'user_id' => auth()->id(),
            'message_text' => $cleanText,
            'recipient_id' => $this->recipientId,
            'is_system' => false,
        ]);

        $this->messageText = '';

        // Broadcast to Pusher/Socket.io
        broadcast(new ChatMessageSent($message))->toOthers();

        // Locally append message immediately for instant feedback
        $this->handleIncomingMessage([
            'messageData' => [
                'id' => $message->id,
                'game_session_id' => $message->game_session_id,
                'user_id' => $message->user_id,
                'user_name' => auth()->user()->name,
                'message_text' => $message->message_text,
                'recipient_id' => $message->recipient_id,
                'is_system' => $message->is_system,
                'created_at' => $message->created_at->toIso8601String(),
            ]
        ]);
    }

    public function setPrivateRecipient(?int $userId): void
    {
        $this->recipientId = $userId;
    }

    public function render()
    {
        return view('livewire.quiz-chat');
    }
}
