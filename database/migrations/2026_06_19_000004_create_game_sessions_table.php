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
        Schema::create('game_sessions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->string('pin', 6)->unique();
            $table->string('status', 20)->default('active'); // active, finished
            $table->foreignId('current_question_id')->nullable()->constrained('questions')->onDelete('set null');
            $table->timestamp('current_question_started_at')->nullable();
            $table->timestamps();

            $table->index(['pin', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
