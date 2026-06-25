<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Admin\AdminQuizController;
use App\Http\Controllers\Admin\AdminHistoryController;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\LogUserAction;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth & Captcha
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/captcha', [AuthController::class, 'generateCaptcha']);

// Public Quiz Access
Route::get('/quizzes', [QuizController::class, 'index']);
Route::get('/quizzes/{id}', [QuizController::class, 'show']);
Route::post('/sessions/join', [QuizController::class, 'join']);

// Authenticated Routes
Route::middleware('auth:sanctum')->group(static function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Game Operations
    Route::post('/sessions/{id}/answer', [QuizController::class, 'submitAnswer']);
    Route::get('/sessions/{id}/leaderboard', [LeaderboardController::class, 'sessionLeaderboard']);

    // Admin panel (requires custom IsAdmin middleware & logs all actions)
    Route::middleware([IsAdmin::class, LogUserAction::class])->prefix('admin')->group(static function (): void {
        Route::post('/quizzes', [AdminQuizController::class, 'store']);
        Route::put('/quizzes/{quiz}', [AdminQuizController::class, 'update']);
        Route::delete('/quizzes/{quiz}', [AdminQuizController::class, 'destroy']);
        Route::post('/quizzes/{quiz}/questions', [AdminQuizController::class, 'addQuestion']);
        Route::post('/users/assign-role', [AdminQuizController::class, 'assignRole']);
        
        Route::get('/history', [AdminHistoryController::class, 'gameHistory']);
        Route::get('/history/session/{id}', [AdminHistoryController::class, 'sessionDetails']);
        Route::get('/logs', [AdminHistoryController::class, 'userLogs']);
    });
});
