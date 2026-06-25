<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class AdminQuizController extends Controller
{
    /**
     * Store a newly created quiz.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'rounds_count' => ['required', 'integer', 'min:1'],
        ]);

        $quiz = Quiz::create(array_merge($validated, [
            'creator_id' => $request->user()->id,
            'status' => 'draft',
        ]));

        return response()->json([
            'message' => 'Викторина успешно создана.',
            'quiz' => $quiz,
        ], 201);
    }

    /**
     * Update the specified quiz.
     */
    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'string'],
            'rounds_count' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:draft,active,finished'],
        ]);

        $quiz->update($validated);

        return response()->json([
            'message' => 'Викторина успешно обновлена.',
            'quiz' => $quiz,
        ]);
    }

    /**
     * Soft delete a quiz.
     */
    public function destroy(Quiz $quiz): JsonResponse
    {
        $quiz->delete();

        return response()->json([
            'message' => 'Викторина успешно удалена (помещена в архив).',
        ]);
    }

    /**
     * Add a question to the quiz with optional image processing using Intervention Image.
     */
    public function addQuestion(Request $request, Quiz $quiz): JsonResponse
    {
        $validated = $request->validate([
            'question_text' => ['required', 'string'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4', 'max:20480'], // 20MB limit
            'timer_seconds' => ['required', 'integer', 'between:10,120'],
            'options' => ['required', 'array', 'min:1', 'max:4'],
            'options.*.option_text' => ['required', 'string'],
            'options.*.is_correct' => ['required', 'boolean'],
        ]);

        // Process media file if uploaded
        $mediaPath = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = uniqid('media_', true) . '.' . $extension;

            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                $mediaType = 'image';
                $mediaPath = 'quizzes/images/' . $filename;
                
                // Crop and compress image using Intervention Image
                $img = Image::make($file->getRealPath());
                $img->fit(800, 450, function ($constraint) {
                    $constraint->upsize();
                });
                
                Storage::disk('public')->put($mediaPath, (string) $img->encode($extension, 85));
            } elseif ($extension === 'mp4') {
                $mediaType = 'video';
                $mediaPath = $file->storeAs('quizzes/videos', $filename, 'public');
            }
        }

        // Determine question order (append to end)
        $order = $quiz->questions()->count() + 1;

        $question = $quiz->questions()->create([
            'question_text' => $validated['question_text'],
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
            'timer_seconds' => $validated['timer_seconds'],
            'order' => $order,
        ]);

        // Create options
        foreach ($validated['options'] as $optionData) {
            $question->options()->create([
                'option_text' => $optionData['option_text'],
                'is_correct' => (bool) $optionData['is_correct'],
            ]);
        }

        return response()->json([
            'message' => 'Вопрос успешно добавлен.',
            'question' => $question->load('options'),
        ], 201);
    }

    /**
     * Assign moderator/admin role to a user.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'string', 'in:admin,moderator,player'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        
        // Prevent admins from demoting themselves
        if ($user->id === $request->user()->id && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Вы не можете понизить самого себя.',
            ], 403);
        }

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => "Пользователю {$user->name} успешно присвоена роль {$validated['role']}.",
            'user' => $user,
        ]);
    }
}
