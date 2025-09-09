<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ExamResultController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validate the incoming data from the ReviewPage
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'student_name' => 'required|string|max:255',
            'final_score' => 'required|numeric',
            'total_possible_marks' => 'required|integer',
            'grades' => 'required|array',
            'grades.*.question_number' => 'required|integer',
            'grades.*.student_answer' => 'required|string',
            'grades.*.score' => 'required|numeric',
        ]);

        // We need the original exam questions to link our answers correctly
        $exam = Exam::with('questions')->find($validated['exam_id']);

        // Use a transaction to ensure data integrity
        DB::beginTransaction();
        try {
            // 2. Create the main ExamSubmission record
            $submission = $request->user()->examSubmissions()->create([
                'exam_id' => $validated['exam_id'],
                'student_name' => $validated['student_name'],
                'final_score' => $validated['final_score'],
                'total_possible_marks' => $validated['total_possible_marks'],
                'ai_raw_grades' => $validated['grades'], // Store the AI grades for reference
            ]);

            // 3. Loop through the final, corrected grades and save each one
            foreach ($validated['grades'] as $grade) {
                // Find the original question from the answer key to get its ID
                $question = $exam->questions->firstWhere('question_number', $grade['question_number']);

                if ($question) {
                    $submission->studentAnswers()->create([
                        'question_id' => $question->id,
                        'student_answer' => $grade['student_answer'],
                        'final_score' => $grade['score'],
                    ]);
                }
            }

            // If everything succeeds, commit to the database
            DB::commit();

            // 4. Return a successful response
            return response()->json([
                'message' => 'Final grade saved successfully!',
                'submission_id' => $submission->id
            ], 201); // 201 Created

        } catch (\Exception $e) {
            // If anything fails, roll back all database changes
            DB::rollBack();

            report($e);
            return response()->json(['message' => 'An error occurred while saving the final grade.'], 500);
        }
    }
}