<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class AnswerKeyController extends Controller
{
    /**
     * Scans one or more images of an answer key and returns a consolidated list of questions.
     */
    public function scan(Request $request): JsonResponse
    {
        // 1. Validate for an array of images
        $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['message' => 'Gemini API key is not configured.'], 500);
        }

        $consolidatedQuestions = [];

        // 2. Loop through each uploaded image file
        foreach ($request->file('images') as $imageFile) {
            try {
                $aiResponse = $this->callScanApi($apiKey, $imageFile);

                if ($aiResponse && isset($aiResponse['questions']) && is_array($aiResponse['questions'])) {
                    $consolidatedQuestions = array_merge($consolidatedQuestions, $aiResponse['questions']);
                }
            } catch (\Exception $e) {
                report($e); // Log errors but continue
            }
        }

        if (empty($consolidatedQuestions)) {
            return response()->json(['message' => 'Could not extract any questions from the provided images.'], 500);
        }
        
        // 3. Return the single, consolidated list
        return response()->json(['questions' => $consolidatedQuestions]);
    }
    
    /**
     * A private helper method to call the Gemini API for a single image.
     */
    private function callScanApi(string $apiKey, $imageFile): ?array
    {
        $mimeType = $imageFile->getMimeType();
        $imageBase64 = base64_encode($imageFile->get());
        
        // ✅ FIXED: The full, smart prompt is now correctly defined here.
        $prompt = <<<PROMPT
        You are an expert Optical Character Recognition (OCR) system for educators. Analyze the provided image of a handwritten answer key.
        **INSTRUCTIONS:**
        1. Identify each question number and its corresponding answer.
        2. For each answer, determine its most likely question type. The valid types are "MCQ" (for single letters like A, B, C, D), "TF" (for "True", "False", "T", "F"), and "SHORT" (for any other word or phrase).
        3. You MUST respond with ONLY a valid JSON object. Do not include any other text, explanations, or markdown formatting like ```json.
        4. The JSON object must have a single key named "questions".
        5. The value of "questions" must be an array of objects.
        6. Each object must have two keys: "answer" (string) and "type" (string, one of "MCQ", "TF", or "SHORT").
        Example of your required JSON output:
        { "questions": [ { "answer": "C", "type": "MCQ" }, { "answer": "False", "type": "TF" } ] }
        PROMPT;

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
    }
}