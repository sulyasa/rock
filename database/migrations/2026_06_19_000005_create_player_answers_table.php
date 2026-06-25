<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_answers', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->foreignId('answer_option_id')->constrained('answer_options')->onDelete('cascade');
            $table->unsignedInteger('response_time_ms'); // Time taken to answer in milliseconds
            $table->integer('points')->default(0);
            $table->boolean('is_correct')->default(false);
            $table->boolean('is_fastest')->default(false);
            $table->timestamps();

            // Enforce single answer per player per question in a session
            $table->unique(['game_session_id', 'user_id', 'question_id']);
            $table->index(['game_session_id', 'is_correct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_answers');
    }
};
