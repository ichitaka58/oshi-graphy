<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    // Gemini APIのエンドポイント
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    public function generate(?string $previousInteractionId, string $userPrompt): array
    {
        //  AIに渡す最初のルール文
        $system = <<<TXT
        あなたは日本語のライティングアシスタントです。
        ユーザーが入力した情報（日時、場所、アーティスト名、曲、印象に残ったことなど）を理解し、
        「推し活日記」の文案を作成してください。

        ## 重要ルール
        - ユーザーからの情報をもとに、Google検索ツールを使ってアーティストの最新情報（正式なツアー名やセットリスト、当日の様子など）を調べ、文案に自然に盛り込んでください。
        - **出力テキストには、[cite]などの引用マーカーや注釈は絶対に含めないでください。純粋な日記の本文のみを出力してください。**

        ## 文体と構成
        - 感情豊かで、臨場感のある「推し活」らしい自然な日本語。
        - 長さは **400文字〜600文字程度** を目安にしてください（短するぎると熱量が伝わりません）。
        - 段落分けは自然に行い、箇条書きや見出しは使用しないでください。

        ## エラーハンドリング
        - 情報が不足して文案が作れない場合のみ、必要な要素を一つだけ質問してください。
        TXT;

        // Geminiに送るデータ
        $payload = [
            'model' => config('services.gemini.model'),
            'system_instruction' => $system,
            'input' => $userPrompt,
            'tools' => [
                ['type' => 'google_search'],
            ],
            'generation_config' => [
                'temperature' => 0.7,
                'max_output_tokens' => 2048,
                'top_p' => 0.9,
            ],
        ];

        // 会話のidを'previous_interaction_id'に入れることで、会話の履歴を追う
        if ($previousInteractionId !== null) {
            $payload['previous_interaction_id'] = $previousInteractionId;
        }


        //  APIを呼び出す（45秒でタイムアウト）
        $res = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini.api_key'),
        ])
            ->connectTimeout(5)
            ->timeout(45)
            ->retry(2, 800, throw: false)
            ->post(self::ENDPOINT, $payload); // $thisではなくselfを使う

        // 失敗したらログに残してエラーにする
        if (!$res->successful()) {
            Log::warning('Gemini API error', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
            throw new \RuntimeException('AI回答の生成に失敗しました。しばらくして再度お試しください。');
        }

        //  成功したらレスポンスのJSONを取り出す
        $data = $res->json();
        // 会話のidを取り出す
        $interactionId = data_get($data, 'id');

        // data_get:ネストされた配列やオブジェクトからstepsの配列を取得、存在しない場合、空配列を返す
        $text = collect(data_get($data, 'steps', [])) // 配列をコレクションに変換
            ->where('type', 'model_output')
            ->flatMap(fn($step) => data_get($step, 'content', []))
            ->where('type', 'text')
            ->pluck('text') // 各パーツから'text'キーの値だけを取り出す
            ->implode(''); // それらを空文字で連結する

        // blank():nullも空文字もfalseも全部チェック
        if (blank($text)) {
            // なぜ空だったのかをログに残す
            Log::error('Gemini API Error: Text is empty', ['response_data' => $data]);
            throw new \RuntimeException('AIから有効なテキストが返りません');
        }


        return ['text' => $text, 'interaction_id' => $interactionId];
    }
}
