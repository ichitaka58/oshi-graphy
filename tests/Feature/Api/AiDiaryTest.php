<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

function fakeGeminiResponse(string $text = 'AIが作成した推し活日記の文案です。', string $interactionId = 'interaction-123'): array
{
    return [
        'id' => $interactionId,
        'steps' => [
            [
                'type' => 'model_output',
                'content' => [
                    ['type' => 'text', 'text' => $text],
                ],
            ],
        ],
    ];
}

it('【AI文案作成テスト】未認証では利用できない', function () {
    $response = $this->postJson('/api/ai/diary-suggest', [
        'prompt' => 'テストのプロンプトです',
    ]);

    $response->assertStatus(401);
});

it('【AI文案作成テスト】promptを送るとGeminiの回答を返す', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiResponse(), 200),
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/ai/diary-suggest', [
        'prompt' => 'ライブに行った感想を書きたいです',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'reply' => 'AIが作成した推し活日記の文案です。',
        'interaction_id' => 'interaction-123',
    ]);
    Http::assertSent(function ($request) {
        return $request['input'] === 'ライブに行った感想を書きたいです'
            && !array_key_exists('previous_interaction_id', $request->data());
    });
});

it('【AI文案作成テスト】interaction_idを送ると会話の続きとしてGeminiに送信される', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiResponse(), 200),
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/ai/diary-suggest', [
        'interaction_id' => 'interaction-000',
        'prompt' => '続きの会話です',
    ]);

    $response->assertStatus(200);
    Http::assertSent(function ($request) {
        return $request['previous_interaction_id'] === 'interaction-000';
    });
});

it('【AI文案作成テスト】promptが空の場合はバリデーションエラー', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/ai/diary-suggest', [
        'prompt' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('prompt');
});

it('【AI文案作成テスト】promptが2000文字を超える場合はバリデーションエラー', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/ai/diary-suggest', [
        'prompt' => str_repeat('あ', 2001),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('prompt');
});

it('【AI文案作成テスト】Gemini APIがエラーを返した場合は422でエラーメッセージを返す', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/ai/diary-suggest', [
        'prompt' => 'ライブに行った感想を書きたいです',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'ok' => false,
        'message' => 'AI処理に失敗しました。短い入力で再実行してください。',
    ]);
});

it('【AI文案作成テスト】Geminiの回答テキストが空の場合は422でエラーメッセージを返す', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(fakeGeminiResponse(text: ''), 200),
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/ai/diary-suggest', [
        'prompt' => 'ライブに行った感想を書きたいです',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['ok' => false]);
});
