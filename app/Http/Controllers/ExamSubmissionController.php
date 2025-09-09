<?php

namespace App\Http\Controllers;

use App\Models\Exam; // <-- Make sure this is imported
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ExamSubmissionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1. Validation (remains the same)
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:20480',
        ]);

        // 2. Fetch the Exam and its Questions (the Answer Key)
        $exam = Exam::with('questions')->find($validated['exam_id']);
        if (!$exam) {
            return response()->json(['message' => 'Exam not found.'], 404);
        }

        // 3. Format the Answer Key into a string for the AI
        $answerKeyString = $exam->questions->map(function ($question, $index) {
            return "Question " . ($index + 1) . ": " . $question->correct_answer . " (" . $question->marks . " marks)";
        })->implode("\n");

        // 4. Get the API Key
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['message' => 'Gemini API key is not configured.'], 500);
        }

        // 5. Process the image
        $imageFile = $request->file('image');
        $mimeType = $imageFile->getMimeType();
        $imageBase64 = base64_encode($imageFile->get());

        // 6. ✨ THE NEW, POWERFUL GRADING PROMPT ✨
        $prompt = <<<PROMPT
        You are an AI Exam Grading Assistant. Your task is to analyze an image of a student's handwritten answers and compare it against the provided answer key.

        **ANSWER KEY:**
        {$answerKeyString}

        **INSTRUCTIONS:**
        1.  Read the student's handwritten answers from the provided image.
        2.  For each question, compare the student's answer to the corresponding answer in the key. Be lenient with minor spelling mistakes (e.g., "Pairs" vs "Paris").
        3.  Determine the score for each question based on the marks provided in the answer key. A correct answer gets full marks, an incorrect answer gets 0.
        4.  You MUST respond with ONLY a valid JSON object. Do not include any other text, explanations, or markdown formatting like ```json.
        5.  The JSON object must have a key named "grades" which is an array of objects.
        6.  Each object in the "grades" array must have three keys: "question_number" (integer corresponding to the position in the answer key), "student_answer" (string of what you read), and "score" (integer).

        Example of your required JSON output:
        {
          "grades": [
            { "question_number": 1, "student_answer": "Paris", "score": 1 },
            { "question_number": 2, "student_answer": "True", "score": 1 },
            { "question_number": 3, "student_answer": "Mitochondria", "score": 0 }
          ]
        }
        PROMPT;

        // 7. Build the API payload with the correct keys
        $payload = [
            'contents' => [
                ['parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]]
                ]]
            ],
            'generation_config' => [
                'response_mime_type' => 'application/json',
            ],
        ];
        
        // 8. The API URL (using the fast and reliable 1.5-flash model)
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key={$apiKey}";
        
        try {
            // 9. Send the request with our generous timeout
            $response = Http::timeout(300)->post($url, $payload);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'The AI service returned an error.',
                    'error_details' => $response->json()
                ], 500);
            }

            // 10. Robustly extract and clean the JSON response
            $aiResponseJson = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (!$aiResponseJson) {
                return response()->json([
                    'message' => 'Could not extract a valid text response from the AI.',
                    'raw_ai_response' => $response->json()
                ], 500);
            }

            // Strip potential markdown fences from the AI response
            $cleanedJson = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $aiResponseJson);
            $gradedData = json_decode($cleanedJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => 'The AI returned invalid JSON after cleaning.',
                    'raw_ai_text' => $aiResponseJson
                ], 500);
            }

            return response()->json([
                'ai_grades' => $gradedData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred while contacting the AI service.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}