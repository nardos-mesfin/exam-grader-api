<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ExamSubmissionController extends Controller
{
    // This single method will now handle both single and multi-page uploads.
    public function process(Request $request): JsonResponse
    {
        // 1. Validate the request
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'pages' => 'required|array|min:1',
            'pages.*' => 'required|image|mimes:jpeg,png,jpg|max:20480',
        ]);

        $exam = Exam::with('questions')->find($validated['exam_id']);
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey || !$exam) {
            return response()->json(['message' => 'Invalid request or API key not configured.'], 400);
        }

        $uploadedPages = $request->file('pages');
        $studentName = 'Unknown Student';
        $consolidatedGrades = [];
        $questionCounter = 1;

        // 2. Loop through each uploaded image file
        foreach ($uploadedPages as $index => $pageFile) {
            $isFirstPage = ($index === 0);

            // 3. Select the correct prompt
            if ($isFirstPage) {
                $prompt = $this->getGradingAndNamePrompt($exam);
            } else {
                $prompt = $this->getOcrOnlyPrompt();
            }

            // 4. Call the Gemini API for the current page
            $aiResponse = $this->callGeminiApi($apiKey, $prompt, $pageFile);
            if (!$aiResponse) continue; // Skip failed pages

            // 5. Process the response and add to our consolidated results
            if ($isFirstPage) {
                $studentName = $aiResponse['student_name'] ?? $studentName;
                $gradesFromPage = $aiResponse['grades'] ?? [];
            } else {
                $gradesFromPage = $aiResponse['grades'] ?? [];
            }

            foreach ($gradesFromPage as $grade) {
                $consolidatedGrades[] = [
                    'question_number' => $questionCounter++,
                    'student_answer' => $grade['student_answer'] ?? 'N/A',
                    'score' => $grade['score'] ?? 0,
                ];
            }
        }
        
        return response()->json([
            'ai_results' => [
                'student_name' => $studentName,
                'grades' => $consolidatedGrades
            ],
            'answer_key' => $exam->questions,
        ]);
    }

    // --- HELPER METHODS ---

    private function getGradingAndNamePrompt(Exam $exam): string
    {
        $answerKeyString = $exam->questions->map(function ($question, $index) {
            return "Question " . ($index + 1) . ": " . $question->correct_answer . " (" . $question->marks . " marks)";
        })->implode("\n");

        return <<<PROMPT
        You are an AI Exam Grading Assistant. Your task is to analyze an image of a student's handwritten answers, extract their name, and grade their answers against the provided key.

        **ANSWER KEY:**
        {$answerKeyString}

        **INSTRUCTIONS:**
        1.  First, locate and extract the student's full name from the top of the exam paper.
        2.  Read the student's handwritten answers for each question on this page.
        3.  Grade the answers against the answer key. Be lenient with minor spelling mistakes.
        4.  You MUST respond with ONLY a valid JSON object. The JSON object must have a key named "student_name" (string) and a key named "grades" (an array of objects with "student_answer" and "score" keys).
        PROMPT;
    }

    private function getOcrOnlyPrompt(): string
    {
        return <<<PROMPT
        You are an AI Grading Assistant. You are processing page 2 or later of a multi-page exam. The answer key is not provided for this page.
        
        **INSTRUCTIONS:**
        1.  Read the student's handwritten answers for each question on this page, in order.
        2.  You MUST respond with ONLY a valid JSON object.
        3.  The JSON object must have a key named "grades" which is an array of objects.
        4.  Each object in the "grades" array must have ONLY two keys: "student_answer" (string) and "score" (integer, which should always be 0 for this page).
        PROMPT;
    }

    private function callGeminiApi(string $apiKey, string $prompt, $imageFile): ?array
    {
        try {
            $mimeType = $imageFile->getMimeType();
            $imageBase64 = base64_encode($imageFile->get());

            $payload = [
                'contents' => [['parts' => [['text' => $prompt], ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageBase64]]]]],
                'generation_config' => ['response_mime_type' => 'application/json']
            ];

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";
            
            $response = Http::timeout(120)->post($url, $payload);

            if (!$response->successful()) {
                return null;
            }

            $aiResponseJson = data_get($response->json(), 'candidates.0.content.parts.0.text');
            $cleanedJson = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $aiResponseJson);
            
            return json_decode($cleanedJson, true);

        } catch (\Exception $e) {
            report($e);
            return null;
        }
    }
}