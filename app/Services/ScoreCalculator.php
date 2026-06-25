<?php

declare(strict_types=1);

namespace App\Services;

class ScoreCalculator
{
    private const FASTEST_ANSWER_BONUS = 50;
    private const INCORRECT_ANSWER_PENALTY = 10;

    /**
     * Calculate points for a given answer.
     *
     * @param bool $isCorrect Whether the answer is correct
     * @param int $timerSeconds Total timer duration for the question in seconds
     * @param int $responseTimeMs Player's response time in milliseconds
     * @param bool $isFastest Whether this player was the fastest to answer correctly
     * @return int Calculated points
     */
    public function calculatePoints(
        bool $isCorrect,
        int $timerSeconds,
        int $responseTimeMs,
        bool $isFastest = false
    ): int {
        if (!$isCorrect) {
            return -self::INCORRECT_ANSWER_PENALTY;
        }

        // Convert response time to seconds
        $responseTimeSeconds = $responseTimeMs / 1000.0;

        // Points based on remaining time (clamped between 0 and total timer duration)
        $remainingTime = (float) $timerSeconds - $responseTimeSeconds;
        $basePoints = (int) max(0.0, round($remainingTime));

        // Add bonus points if this player is the fastest to answer correctly
        if ($isFastest) {
            $basePoints += self::FASTEST_ANSWER_BONUS;
        }

        return $basePoints;
    }
}
