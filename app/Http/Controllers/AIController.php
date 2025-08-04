<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client as GuzzleClient;

class AIController extends Controller
{

    public function index()
    {
        return view('ai');
    }


    public function submitPrompt(Request $request)
    {
        // validate the request data
        $request->validate([
            'model' => 'required|string',
            'prompt' => 'required|string',
            'dataset' => 'nullable|string',
        ]);

        $prompt = $request->input('prompt');
        $model = $request->input('model');
        $dataset = $request->input('dataset');
        $message = null;
        $messages = [];

        // add dataset to message if it exists
        if (!empty($dataset)) {
            $messages[] = [
                'role' => 'system',
                'content' => $dataset,
            ];
        }
        // add user prompt
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        try {
            $client = new GuzzleClient();

            $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $message = $data['choices'][0]['message']['content'] ?? 'No response.';

            return response()->json([
                'message' => $message,
                'prompt' => $prompt,
                'model' => $model,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
