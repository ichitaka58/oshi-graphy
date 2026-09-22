<?php

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Follow;
use App\Models\Message;
use App\Models\User;

beforeEach(function () {
    /** @var \Tests\TestCase $this */ //赤い波線を消すおまじない
    $this->me = User::factory()->create(); // 先に書いたのでidが小さい
    $this->target = User::factory()->create(); // meよりもidが大きい
    Follow::create(['follower_id' => $this->me->id, 'followed_id' => $this->target->id]);
    Follow::create(['follower_id' => $this->target->id, 'followed_id' => $this->me->id]);
    $this->conversation = Conversation::create(['user_one_id' => $this->me->id, 'user_two_id' => $this->target->id]);
    $this->token = $this->me->createToken('test_token')->plainTextToken;
});

it('【メッセージ送信】参加者はメッセージを送れる', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->withToken($this->token)->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => 'テストメッセージ']);

    $response->assertStatus(201);
    $this->assertDatabaseHas('messages', [
        'conversation_id' => $this->conversation->id,
        'sender_id' => $this->me->id,
        'body' => 'テストメッセージ',
    ]);
});

it('【メッセージ送信】送信でlast_message_atが更新され、送信者側のread_atが入る（相手側はnull)', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->withToken($this->token)->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => 'テストメッセージ']);

    $response->assertStatus(201);

    // findOrFail(): 該当するidのレコードを1件取得、なければ例外を投げる
    //（HTTPリクエスト中ならLaravelがそれを404に変換する）
    $message = Message::findOrFail($response->json('message.id'));
    $this->conversation->refresh();

    expect($this->conversation->last_message_at->eq($message->created_at))->toBeTrue();
    expect($this->conversation->user_one_read_at->eq($message->created_at))->toBeTrue();
    expect($this->conversation->user_two_read_at)->toBeNull();
});

it('【メッセージ送信】bodyが空だと送れない', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->withToken($this->token)->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => '']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('body');
    $this->assertDatabaseCount('messages', 0);
});

it('【メッセージ送信】bodyが2001文字だと送れない', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->withToken($this->token)->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => str_repeat('あ', 2001)]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('body');
    $this->assertDatabaseCount('messages', 0);
});

it('【メッセージ送信】第三者は送れない', function () {
    /** @var \Tests\TestCase $this */
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('test_token')->plainTextToken;
    $response = $this->withToken($otherToken)->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => 'テストメッセージ']);

    $response->assertStatus(403);
    $this->assertDatabaseCount('messages', 0);
});

it('【メッセージ送信】相互フォローが解除されたら送れない', function () {
    /** @var \Tests\TestCase $this */
    Follow::where('follower_id', $this->me->id)->where('followed_id', $this->target->id)->delete();
    $response = $this->withToken($this->token)->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => 'テストメッセージ']);

    $response->assertStatus(403);
    $this->assertDatabaseCount('messages', 0);
});

it('【メッセージ送信】ブロックされたら送れない', function () {
    /** @var \Tests\TestCase $this */
    $this->conversation->messages()->create(['sender_id' => $this->me->id, 'body' => '過去のメッセージ']);
    Block::create(['blocker_id' => $this->target->id, 'blocked_id' => $this->me->id]);
    $response = $this->withToken($this->token)->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => 'テストメッセージ']);

    $response->assertStatus(403);
    $this->assertDatabaseCount('messages', 1);
});

it('【メッセージ送信】未認証では送れない', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => 'テストメッセージ']);

    $response->assertStatus(401);
    $this->assertDatabaseCount('messages', 0);
});
