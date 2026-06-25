<div class="glass-card" style="display: flex; flex-direction: column; height: 500px; padding: 1.5rem;" x-data="chatComponent()">
    <div style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; font-weight: 700; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.75rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
        <span>Чат игры</span>
        <!-- Recipient indicator -->
        <select wire:model="recipientId" style="background: rgba(15, 23, 42, 0.6); color: white; border: 1px solid var(--border-glass); border-radius: 8px; font-size: 0.8rem; padding: 0.25rem 0.5rem; outline: none;">
            <option value="">Всем в комнате</option>
            <!-- In production, we loop through active session users. Let's provide a static placeholder for UI -->
            <option value="1">Администратор (Приватный)</option>
        </select>
    </div>

    <!-- Messages Container -->
    <div id="chatMessagesList" class="messages-list" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem; padding-right: 0.5rem; margin-bottom: 1rem;">
        @forelse($messages as $msg)
            @if($msg['is_system'])
                <div class="system-message" style="text-align: center; font-size: 0.8rem; color: var(--text-muted); font-style: italic; background: rgba(255, 255, 255, 0.02); padding: 0.35rem; border-radius: 8px;">
                    {{ $msg['message_text'] }}
                </div>
            @else
                @php
                    $isPrivate = !is_null($msg['recipient_id']);
                    $isOwnMessage = $msg['user_id'] === auth()->id();
                @endphp
                <div class="message-bubble" style="display: flex; flex-direction: column; max-width: 80%; border-radius: 12px; padding: 0.75rem; font-size: 0.9rem; 
                    {{ $isOwnMessage ? 'align-self: flex-end; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(168, 85, 247, 0.2) 100%); border: 1px solid rgba(168, 85, 247, 0.3);' : 'align-self: flex-start; background: rgba(30, 41, 59, 0.6); border: 1px solid var(--border-glass);' }}
                    {{ $isPrivate ? 'border-left: 4px solid var(--accent-gradient);' : '' }}">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.25rem;">
                        <span style="font-weight: 600; color: #a855f7; font-size: 0.8rem;">
                            {{ $msg['user']['name'] }}
                            @if($isPrivate)
                                <span style="color: #f43f5e; font-weight: normal; font-size: 0.75rem;">(Приватное)</span>
                            @endif
                        </span>
                        <span style="font-size: 0.7rem; color: var(--text-muted);">
                            {{ \Carbon\Carbon::parse($msg['created_at'])->format('H:i') }}
                        </span>
                    </div>
                    <span style="word-break: break-word; color: white;">{{ $msg['message_text'] }}</span>
                </div>
            @endif
        @empty
            <div style="text-align: center; color: var(--text-muted); margin-top: auto; margin-bottom: auto; font-size: 0.9rem;">
                Сообщений пока нет. Будьте первыми!
            </div>
        @endforelse
    </div>

    <!-- Message Input and Emojis Panel -->
    <div class="chat-input-panel" style="border-top: 1px solid var(--border-glass); padding-top: 1rem;">
        <!-- Simple Emoji Row -->
        <div class="emoji-row" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem;">
            @foreach(['😀', '😂', '🔥', '🏆', '🎉', '💡', '😱', '👍', '👎', '❤️'] as $emoji)
                <button 
                    type="button" 
                    @click="insertEmoji('{{ $emoji }}')"
                    style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; padding: 0.25rem; border-radius: 6px; transition: background 0.2s;"
                    onmouseover="this.style.background='rgba(255, 255, 255, 0.05)'"
                    onmouseout="this.style.background='transparent'"
                >
                    {{ $emoji }}
                </button>
            @endforeach
        </div>

        <form wire:submit.prevent="sendMessage" style="display: flex; gap: 0.5rem;">
            <input 
                id="messageTextInputField"
                type="text" 
                wire:model.defer="messageText" 
                placeholder="Напишите сообщение..." 
                class="input-field"
                style="flex: 1; padding: 0.75rem 1rem;"
                x-ref="messageInput"
            >
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;">
                Отправить
            </button>
        </form>
    </div>

    <script>
        function chatComponent() {
            return {
                init() {
                    this.$wire.on('scrollChatToBottom', () => {
                        this.$nextTick(() => {
                            const container = document.getElementById('chatMessagesList');
                            if (container) {
                                container.scrollTop = container.scrollHeight;
                            }
                        });
                    });

                    // Scroll to bottom on initial load
                    this.$nextTick(() => {
                        const container = document.getElementById('chatMessagesList');
                        if (container) {
                            container.scrollTop = container.scrollHeight;
                        }
                    });
                },

                insertEmoji(emoji) {
                    const input = this.$refs.messageInput;
                    const val = input.value;
                    const start = input.selectionStart;
                    const end = input.selectionEnd;
                    
                    // Insert emoji at cursor position
                    const text = val.substring(0, start) + emoji + val.substring(end);
                    input.value = text;
                    
                    // Trigger livewire data update
                    this.$wire.set('messageText', text);
                    
                    // Restore cursor focus
                    input.focus();
                    this.$nextTick(() => {
                        input.setSelectionRange(start + emoji.length, start + emoji.length);
                    });
                }
            }
        }
    </script>
</div>
