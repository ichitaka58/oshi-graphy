<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeminiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiDiaryController extends Controller
{
    public function suggest(Request $request, GeminiClient $gemini)
    {
        $data = $request->validate([
            'prompt' => 'required|string|max:2000',
            'interaction_id' => 'nullable|string'
        ]);

        try {
            $result = $gemini->generate($data['interaction_id'] ?? null, $data['prompt']);

            return response()->json([
                'ok' => true,
                'reply' => $result['text'],
                'interaction_id' => $result['interaction_id']
            ], 200);

        } catch (\Throwable $e) {
            Log::warning('AI suggest failed', ['error' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'message' => 'AI処理に失敗しました。短い入力で再実行してください。'
            ], 422);
        }
    }
}
