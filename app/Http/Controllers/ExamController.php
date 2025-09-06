<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class ExamController extends Controller
{
    /**
     * Store a newly created exam and its questions in the database.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Update Validation
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject' => 'nullable|string|max:255',
            'total_marks' => 'required|integer', // <-- Add
            'questions' => 'required|array',
            'questions.*.answer' => 'required|string',
            'questions.*.type' => 'required|string', // <-- Add
            'questions.*.marks' => 'required|integer|min:1', // <-- Add
        ]);

        DB::beginTransaction();
        try {
            // 2. Update Exam Creation
            $exam = $request->user()->exams()->create([
                'title' => $validated['title'],
                'subject' => $validated['subject'],
                'total_marks' => $validated['total_marks'], // <-- Add
            ]);

            // 3. Update Question Creation
            foreach ($validated['questions'] as $index => $questionData) {
                $exam->questions()->create([
                    'question_number' => $index + 1,
                    'correct_answer' => $questionData['answer'],
                    'question_type' => $questionData['type'], // <-- Add
                    'marks' => $questionData['marks'], // <-- Add
                ]);
            }
            
            DB::commit();
            return response()->json($exam->load('questions'), 201);

        } catch (\Exception $e) {
            // If any error occurred, roll back all database changes.
            DB::rollBack();

            // Log the error and return a generic server error message.
            report($e);
            return response()->json(['message' => 'An error occurred while saving the exam.'], 500);
        }
    }
}