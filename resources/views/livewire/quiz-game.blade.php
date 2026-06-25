<div class="glass-card" x-data="quizComponent()" x-init="initQuiz()">
    @if (session()->has('error'))
        <div class="alert-error" style="background: rgba(239, 68, 68, 0.2); border: 1px solid var(--accent-error); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            {{ session('error') }}
        </div>
    @endif

    <div class="quiz-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.8rem; margin: 0;">{{ $session->quiz->title }}</h2>
        <!-- Alpine Countdown Timer -->
        <div class="timer-container" style="display: flex; align-items: center; gap: 0.5rem; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); padding: 0.5rem 1rem; border-radius: 99px;">
            <span style="color: var(--text-muted); font-size: 0.875rem;">Осталось времени:</span>
            <span style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 1.25rem; color: #a855f7;" x-text="timer">0</span>с
        </div>
    </div>

    @if($session->currentQuestion)
        <div class="question-container" style="margin-bottom: 2rem;">
            <div style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                Раунд {{ $session->currentQuestion->order }} из {{ $session->quiz->rounds_count }}
            </div>
            <h3 style="font-size: 1.4rem; font-weight: 600; margin: 0 0 1.5rem 0; line-height: 1.4;">
                {{ $session->currentQuestion->question_text }}
            </h3>

            <!-- Question Media Element -->
            @if($session->currentQuestion->media_path)
                <div class="media-preview" style="border-radius: 16px; overflow: hidden; margin-bottom: 1.5rem; max-height: 350px; background: #000; border: 1px solid var(--border-glass);">
                    @if($session->currentQuestion->media_type === 'image')
                        <img src="/storage/{{ $session->currentQuestion->media_path }}" alt="Media" style="width: 100%; height: auto; object-fit: contain; max-height: 350px;">
                    @elseif($session->currentQuestion->media_type === 'video')
                        <video controls style="width: 100%; max-height: 350px;">
                            <source src="/storage/{{ $session->currentQuestion->media_path }}" type="video/mp4">
                            Ваш браузер не поддерживает встроенные видео.
                        </video>
                    @endif
                </div>
            @endif

            <!-- Answer Options List -->
            <div class="options-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                @foreach($session->currentQuestion->options as $option)
                    @php
                        $optionClass = 'option-btn';
                        if ($hasAnswered) {
                            if ($option->id === $selectedOptionId) {
                                $optionClass .= $showFeedback && $option->is_correct ? ' correct-answer' : ' incorrect-answer';
                            }
                        }
                    @endphp
                    <button 
                        wire:click="selectOption({{ $option->id }})"
                        class="btn-option {{ $optionClass }}"
                        :disabled="timeUp || hasAnswered"
                        style="text-align: left; padding: 1.25rem; border-radius: 16px; border: 1px solid var(--border-glass); background: rgba(30, 41, 59, 0.4); color: white; cursor: pointer; transition: all 0.2s;"
                    >
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span class="option-indicator" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(255, 255, 255, 0.05); font-weight: 600;">
                                {{ chr(65 + $loop->index) }}
                            </span>
                            <span style="font-size: 1.1rem; font-weight: 500;">{{ $option->option_text }}</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Dynamic Round Statistics (Chart.js) -->
        <div x-show="showStats" style="margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid var(--border-glass);">
            <h4 style="font-family: 'Outfit', sans-serif; font-size: 1.2rem; margin: 0 0 1rem 0;">Распределение ответов игроков</h4>
            <div style="position: relative; height: 200px; width: 100%;">
                <canvas id="roundStatsChart"></canvas>
            </div>
        </div>
    @else
        <div style="text-align: center; padding: 3rem 0;">
            <p style="color: var(--text-muted); font-size: 1.2rem;">Ожидание запуска игры организатором...</p>
        </div>
    @endif

    <style>
        .btn-option:hover:not(:disabled) {
            background: rgba(99, 102, 241, 0.1) !important;
            border-color: #6366f1 !important;
            transform: translateY(-2px);
        }
        .correct-answer {
            background: rgba(16, 185, 129, 0.2) !important;
            border-color: var(--accent-success) !important;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }
        .incorrect-answer {
            background: rgba(239, 68, 68, 0.2) !important;
            border-color: var(--accent-error) !important;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
        }
    </style>

    <script>
        function quizComponent() {
            return {
                timer: 0,
                interval: null,
                timeUp: false,
                hasAnswered: @entangle('hasAnswered'),
                showStats: false,
                chart: null,

                initQuiz() {
                    this.$wire.on('newRoundStarted', (question) => {
                        this.startCountdown(question.timer_seconds);
                    });

                    this.$wire.on('renderStatsChart', (stats) => {
                        this.renderChart(stats);
                    });

                    // Set initial timer if question is already active
                    @if($session->currentQuestion && $session->current_question_started_at)
                        @php
                            $elapsed = now()->diffInSeconds($session->current_question_started_at);
                            $remaining = max(0, $session->currentQuestion->timer_seconds - $elapsed);
                        @endphp
                        this.startCountdown({{ $remaining }});
                    @endif
                },

                startCountdown(duration) {
                    clearInterval(this.interval);
                    this.timer = duration;
                    this.timeUp = false;
                    this.showStats = false;

                    this.interval = setInterval(() => {
                        if (this.timer > 0) {
                            this.timer--;
                        } else {
                            this.timeUp = true;
                            clearInterval(this.interval);
                            // Trigger show stats automatically at round end
                            this.$wire.call('showRoundStatistics', {{ $session->current_question_id ?? 0 }});
                        }
                    }, 1000);
                },

                renderChart(statsData) {
                    this.showStats = true;
                    this.$nextTick(() => {
                        const ctx = document.getElementById('roundStatsChart').getContext('2d');
                        if (this.chart) {
                            this.chart.destroy();
                        }

                        const labels = statsData.map(item => item.option_text);
                        const data = statsData.map(item => item.count);

                        this.chart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Количество голосов',
                                    data: data,
                                    backgroundColor: ['rgba(99, 102, 241, 0.6)', 'rgba(168, 85, 247, 0.6)', 'rgba(59, 130, 246, 0.6)', 'rgba(236, 72, 153, 0.6)'],
                                    borderColor: ['#6366f1', '#a855f7', '#3b82f6', '#ec4899'],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: { precision: 0, color: '#94a3b8' },
                                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                                    },
                                    x: {
                                        ticks: { color: '#94a3b8' },
                                        grid: { display: false }
                                    }
                                }
                            }
                        });
                    });
                }
            }
        }
    </script>
</div>
