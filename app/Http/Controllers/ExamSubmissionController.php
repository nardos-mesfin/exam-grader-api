<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http; // Import Laravel's powerful HTTP client, which uses Guzzle

class ExamSubmissionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1. Validation remains the same
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:20480',
        ]);

        // 2. Get the API key from our .env file. If it's missing, fail gracefully.
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['message' => 'Gemini API key is not configured.'], 500);
        }

        // 3. Get the image data
        $imageFile = $request->file('image');
        $mimeType = $imageFile->getMimeType();
        // We need to encode the raw image data into Base64 for the JSON payload
        $imageBase64 = base64_encode($imageFile->get());

        // 4. Build the API request payload EXACTLY as the Gemini documentation requires
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "You are an expert OCR system. Extract all handwritten text from this image. Present the text clearly."],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $imageBase64
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // 5. The API endpoint URL for the Gemini Pro Vision model
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key={$apiKey}";

        
        try {
            // 6. Use Laravel's HTTP client (Guzzle) to send the request
            $response = Http::post($url, $payload);

            // Check if the request was successful
            if (!$response->successful()) {
                // If not, return the error from Google directly
                return response()->json([
                    'message' => 'Failed to process image with AI.',
                    'error' => $response->json() // Google's error message
                ], 500);
            }

            // 7. Extract the text from the successful response
            // The text is nested deep inside the JSON response from Gemini
            $extractedText = $response->json('candidates.0.content.parts.0.text', 'Could not extract text.');

            return response()->json([
                'extracted_text' => $extractedText
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json(['message' => 'An unexpected error occurred while contacting the AI service.'], 500);
        }
    }
}