<div class="glass-card" style="padding: 1.5rem;">
    <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0 0 1.5rem 0; background: var(--secondary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Таблица Лидеров
    </h3>

    <div class="leaderboard-list" style="display: flex; flex-direction: column; gap: 1rem;">
        @php
            $maxScore = count($leaderboard) > 0 ? (int) $leaderboard[0]['score'] : 1;
            // Avoid division by zero
            if ($maxScore <= 0) {
                $maxScore = 1;
            }
        @endphp

        @forelse($leaderboard as $index => $player)
            <div class="leaderboard-item" style="display: flex; flex-direction: column; gap: 0.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <!-- Place Badge -->
                        <span class="rank-badge" style="width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700;
                            {{ $index === 0 ? 'background: #f59e0b; color: #0f172a;' : ($index === 1 ? 'background: #94a3b8; color: #0f172a;' : ($index === 2 ? 'background: #b45309; color: #f8fafc;' : 'background: rgba(255, 255, 255, 0.05); color: var(--text-muted);')) }}">
                            {{ $index + 1 }}
                        </span>
                        <span style="font-weight: 600; font-size: 1rem; color: white;">
                            {{ $player['name'] }}
                        </span>
                    </div>

                    <!-- Player Stats -->
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 0.8rem; color: var(--text-muted); background: rgba(255, 255, 255, 0.03); padding: 0.15rem 0.5rem; border-radius: 4px;">
                            {{ $player['correct_answers'] }} ✓
                        </span>
                        <span style="font-family: 'Outfit', sans-serif; font-weight: 700; color: #a855f7; font-size: 1.1rem;">
                            {{ $player['score'] }}
                        </span>
                    </div>
                </div>

                <!-- Custom Progress Bar -->
                @php
                    $percentage = min(100, max(0, round(($player['score'] / $maxScore) * 100)));
                @endphp
                <div class="progress-track" style="width: 100%; height: 6px; background: rgba(255, 255, 255, 0.05); border-radius: 99px; overflow: hidden;">
                    <div class="progress-fill" style="height: 100%; width: {{ $percentage }}%; background: var(--primary-gradient); border-radius: 99px; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                </div>
            </div>
        @empty
            <div style="text-align: center; color: var(--text-muted); padding: 2rem 0; font-size: 0.95rem;">
                Ждем ответов игроков...
            </div>
        @endforelse
    </div>
</div>
