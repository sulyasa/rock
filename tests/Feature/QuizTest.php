<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Events\QuizStarted;
use App\Models\AnswerOption;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\BadWordFilter;
use App\Services\ScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful game session initialization and broadcasting.
     */
    public function test_quiz_can_be_started_by_admin(): void
    {
        Event::fake([QuizStarted::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $quiz = Quiz::factory()->create(['creator_id' => $admin->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/quizzes/{$quiz->id}/start");

        $response->assertStatus(201);
        $this->assertDatabaseHas('game_sessions', [
            'quiz_id' => $quiz->id,
            'status' => 'active',
        ]);

        Event::assertDispatched(QuizStarted::class);
    }

    /**
     * Test score calculator service details including speed bonus and wrong answers penalty.
     */
    public function test_score_calculation_logic(): void
    {
        $calculator = new ScoreCalculator();

        // 1. Correct answer, 5 seconds elapsed, total 30 seconds timer (no speed bonus)
        // remaining = 30 - 5 = 25 points
        $points = $calculator->calculatePoints(true, 30, 5000, false);
        $this->assertEquals(25, $points);

        // 2. Correct answer, 2 seconds elapsed, total 20 seconds timer (with speed bonus of +50)
        // remaining = 20 - 2 = 18. bonus = 50. total = 68 points
        $pointsWithBonus = $calculator->calculatePoints(true, 20, 2000, true);
        $this->assertEquals(68, $pointsWithBonus);

        // 3. Incorrect answer (penalty should be -10)
        $penaltyPoints = $calculator->calculatePoints(false, 30, 4000);
        $this->assertEquals(-10, $penaltyPoints);
    }

    /**
     * Test chat censorship and bad words filter service.
     */
    public function test_chat_message_moderation(): void
    {
        $filter = new BadWordFilter();

        $dirtyMessage = 'Привет, ты настоящая сука и мудак!';
        $cleanedMessage = $filter->clean($dirtyMessage);

        // Censored words must be replaced with asterisk symbols matching original length
        $this->assertStringNotContainsString('сука', $cleanedMessage);
        $this->assertStringNotContainsString('мудак', $cleanedMessage);
        $this->assertStringContainsString('****', $cleanedMessage);
        $this->assertStringContainsString('*****', $cleanedMessage);
    }

    /**
     * Test private chat message scoping.
     */
    public function test_private_message_scoping(): void
    {
        Event::fake([ChatMessageSent::class]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create(); // Outsider

        $session = GameSession::factory()->create();

        Sanctum::actingAs($user1);

        // Send private message from User1 to User2
        $response = $this->postJson("/api/sessions/{$session->id}/chat", [
            'message_text' => 'Секретная подсказка',
            'recipient_id' => $user2->id,
        ]);

        $response->assertStatus(201);

        // Verify that User3 cannot retrieve this private message
        $this->actingAs($user3);
        $getChatResponse = $this->getJson("/api/sessions/{$session->id}/chat");
        
        $getChatResponse->assertStatus(200);
        $getChatResponse->assertJsonMissing([
            'message_text' => 'Секретная подсказка',
        ]);
    }
}
