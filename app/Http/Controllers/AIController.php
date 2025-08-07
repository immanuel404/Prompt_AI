<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client as GuzzleClient;

class AIController extends Controller
{

    // use Test Database(emulate db)
    private function getTestData()
    {
        return collect([
            ['id' => 1, 'name' => 'John Doe', 'total_sales' => 120],
            ['id' => 2, 'name' => 'Jane Smith', 'total_sales' => 95],
            ['id' => 3, 'name' => 'Alex Johnson', 'total_sales' => 130],
            ['id' => 4, 'name' => 'Emily Brown', 'total_sales' => 75],
            ['id' => 5, 'name' => 'Chris Davis', 'total_sales' => 110],
        ])->map(function ($item) {
            unset($item['id']); // remove 'id' field
            return $item;
        });
    }


    // use AI Models
    private function getOpenRouterModels(): array
    {
        return [
            'deepseek/deepseek-chat-v3-0324:free' => '🧠 DeepSeek Chat V3 (0324)',
            'qwen/qwen3-coder:free' => '💻 Qwen3 Coder',
            'mistralai/mistral-small-3.1-24b-instruct:free' => '📏 Mistral Small 3.1 24B',
            'z-ai/glm-4.5-air:free' => '🌬️ Z.AI GLM 4.5 Air',
        ];
    }


    // format data
    private function formatQueryData($data): string
    {
        if (is_string($data) || is_numeric($data)) {
            return (string) $data;
        }

        if (empty($data)) {
            return 'No data matches your query.';
        }

        $collection = collect($data);

        if ($collection->isEmpty()) {
            return 'No data matches your query.';
        }

        return $collection->map(function ($item) {

            if (is_array($item)) {
                return implode("\n", array_map(
                    fn($k, $v) => ucfirst($k) . ': ' . $v,
                    array_keys($item),
                    $item
                ));
            }

            if (is_object($item)) {
                $vars = get_object_vars($item);
                return implode("\n", array_map(
                    fn($k) => ucfirst($k) . ': ' . $item->$k,
                    array_keys($vars)
                ));
            }

            return (string) $item;
        })->implode("\n\n---\n\n"); // separator between records
    }


    public function index()
    {
        $models = $this->getOpenRouterModels();

        return view('ai', compact('models'));
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
        $dataset = $request->input('dataset');
        $selectedModel = $request->input('model');
        $message = null;
        $messages = [];

        // prioritise selected model
        $allModels = array_keys($this->getOpenRouterModels());
        $prioritizedModels = array_unique(array_merge([$selectedModel], $allModels));

        //  =============== TEST DATA QUERY BASED ON AI PROMPT ===============
        if ($request->boolean('use_testdata_checkbox')) {
            $dataset = "Asses the message given and return a code response based STRICTLY on the user message.
                If the user is asking for the total sales amount return code X1000,
                If the user is asking for the top performing salesman return code X2000,
                If the user is asking for all sales of more than a specified amount return code X3000A along with amount e.g return X3000A/100,
                If the user is asking for all sales of more or equal to a specified amount return code X3000B along with amount e.g return X3000B/100,
                If the user is asking for all sales of less than a specified amount return code X4000A along with amount e.g return X4000A/200,
                If the user is asking for all sales of less or equal to a specified amount return code X4000B along with amount e.g return X4000B/200,
                If the user's request does not match exactly or is even slightly unclear return XXXXX.";
        }
        //  =============== TEST DATA QUERY BASED ON AI PROMPT ===============

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
                    'model' => $prioritizedModels[0],
                    'models' => array_slice($prioritizedModels, 1),
                    'messages' => $messages,
                ],
            ]);
            $data = json_decode($response->getBody(), true);
            // get model name - slice by '/'
            $modelUsed = explode('/', $data['model'])[0];
            // get AI response
            $message = $data['choices'][0]['message']['content'] ?? 'No response.';
            Log::info('AI response: ' . $message);

            //  =============== TEST DATA QUERY BASED ON AI PROMPT ===============
            $query_data = null;
            if ($request->boolean('use_testdata_checkbox')) {
                $salesmen = $this->getTestData();
                $parts = explode('/', $message); // extract threshold value from message
                $threshold = isset($parts[1]) ? (int) $parts[1] : 0; // threshold value

                $query_data = match (true) {
                    str_contains($message, 'X1000') => collect($salesmen)->sum('total_sales'), // sales total
                    str_contains($message, 'X2000') => collect($salesmen)->sortByDesc('total_sales')->first(), // top performer
                    str_contains($message, 'X3000A') => collect($salesmen)->where('total_sales', '>', $threshold)->values(), // sales above threshhold(dynamic_field)
                    str_contains($message, 'X3000B') => collect($salesmen)->where('total_sales', '>=', $threshold)->values(), // sales above/equal to threshhold(dynamic_field)
                    str_contains($message, 'X4000A') => collect($salesmen)->where('total_sales', '<', $threshold)->values(), // sales less than threshhold(dynamic_field)
                    str_contains($message, 'X4000B') => collect($salesmen)->where('total_sales', '<=', $threshold)->values(), // sales less than/equal to threshhold(dynamic_field)
                    default => null,
                };

                // return and format result
                if ($query_data !== null) {
                    $message = $this->formatQueryData($query_data);
                } else {
                    $message = "Sorry, I'm not able to process your query at this time.";
                }
            }
            //  =============== TEST DATA QUERY BASED ON AI PROMPT ===============

            return response()->json([
                'message' => $message,
                'prompt' => $prompt,
                'model' => $modelUsed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
