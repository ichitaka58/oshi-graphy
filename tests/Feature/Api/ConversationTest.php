<?php

use App\Models\Conversation;
use App\Models\Follow;
use App\Models\User;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->me = User::factory()->create();
    $this->target = User::factory()->create();
    $this->token = $this->me->createToken('test_token')->plainTextToken;
});

function makeConversation(User $me, User $target): Conversation
{
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    Follow::create(['follower_id' => $target->id, 'followed_id' => $me->id]);
    $conversation = Conversation::create([
        'user_one_id' => min($me->id, $target->id),
        'user_two_id' => max($me->id, $target->id)
    ]);
    return $conversation;
}


it('【会話作成】相互フォロー中の相手と会話を作成できる', function () {
    /** @var \Tests\TestCase $this */ //赤い波線を消すおまじない
    // idの順序が論点なので、beforeEachは使わずここで順番を決めて作る
    $me = User::factory()->create();
    $target = User::factory()->create();
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    Follow::create(['follower_id' => $target->id, 'followed_id' => $me->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/conversations");

    $response->assertStatus(201);
    $this->assertDatabaseHas('conversations', [
        'user_one_id' => $me->id,
        'user_two_id' => $target->id,
    ]);
});

it('【会話作成】自分のidが相手より大きくても user_one_id に小さい方が入る', function () {
    /** @var \Tests\TestCase $this */
    $target = User::factory()->create();
    $me = User::factory()->create();
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    Follow::create(['follower_id' => $target->id, 'followed_id' => $me->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/conversations");

    $response->assertStatus(201);
    $this->assertDatabaseHas('conversations', [
        'user_one_id' => $target->id,
        'user_two_id' => $me->id,
    ]);
});

it('【会話作成】既に会話があれば作らず同じ会話を返す', function () {
    /** @var \Tests\TestCase $this */
    $conversation = makeConversation($this->me, $this->target);

    $response = $this->withToken($this->token)->postJson("/api/users/{$this->target->id}/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversation.id', $conversation->id);
    $this->assertDatabaseCount('conversations', 1);
});

it('【会話作成】片思い（自分→相手だけフォロー）は作れない', function () {
    /** @var \Tests\TestCase $this */
    Follow::create(['follower_id' => $this->me->id, 'followed_id' => $this->target->id]);

    $response = $this->withToken($this->token)->postJson("/api/users/{$this->target->id}/conversations");

    $response->assertStatus(403);
    $this->assertDatabaseCount('conversations', 0);
});

it('【会話作成】逆片思い（相手→自分だけフォロー）も作れない', function () {
    /** @var \Tests\TestCase $this */
    Follow::create(['follower_id' => $this->target->id, 'followed_id' => $this->me->id]);

    $response = $this->withToken($this->token)->postJson("/api/users/{$this->target->id}/conversations");

    $response->assertStatus(403);
    $this->assertDatabaseCount('conversations', 0);
});

it('【会話作成】自分自身とは作れない', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->withToken($this->token)->postJson("/api/users/{$this->me->id}/conversations");

    $response->assertStatus(403);
    $this->assertDatabaseCount('conversations', 0);
});

it('【会話作成】未認証では作れない', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson("/api/users/{$this->target->id}/conversations");

    $response->assertStatus(401);
    $this->assertDatabaseCount('conversations', 0);
});

it('【会話一覧】自分の会話だけが返る（他人同士の会話は含まれない)', function () {
    /** @var \Tests\TestCase $this */
    $conversation = makeConversation($this->me, $this->target);
    $conversation->messages()->create(['sender_id' => $this->me->id, 'body' => '自分の会話のメッセージ']);
    $otherUser = User::factory()->create();
    $otherConversation = makeConversation($this->target, $otherUser);
    $otherConversation->messages()->create(['sender_id' => $this->target->id, 'body' => '他人同士の会話のメッセージ']);

    $response = $this->withToken($this->token)->getJson("/api/conversations");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'conversations');
    $response->assertJsonPath('conversations.0.id', $conversation->id);
});

it('【会話一覧】other_userと最終メッセージが入る', function () {
    /** @var \Tests\TestCase $this */
    $conversation = makeConversation($this->me, $this->target);
    $conversation->messages()->create(['sender_id' => $this->me->id, 'body' => 'テストメッセージ']);

    $response = $this->withToken($this->token)->getJson("/api/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversations.0.other_user.id', $this->target->id);
    $response->assertJsonPath('conversations.0.last_message.body', 'テストメッセージ');
});

it('【会話一覧】相手の email と内部リレーションは出ない', function () {
    /** @var \Tests\TestCase $this */
    makeConversation($this->me, $this->target);

    $response = $this->withToken($this->token)->getJson("/api/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversations.0.other_user.id', $this->target->id);
    $response->assertJsonMissingPath('conversations.0.other_user.email');
    $response->assertJsonMissingPath('conversations.0.user_one');
});

it('【会話一覧】相手が送った直後は未読', function () {
    /** @var \Tests\TestCase $this */
    $conversation = makeConversation($this->me, $this->target);
    $conversation->messages()->create(['sender_id' => $this->target->id, 'body' => 'テストメッセージ']);

    $response = $this->withToken($this->token)->getJson("/api/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversations.0.is_unread', true);
});

it('【会話一覧】自分が送った直後は既読', function () {
    /** @var \Tests\TestCase $this */
    $conversation = makeConversation($this->me, $this->target);
    $conversation->messages()->create(['sender_id' => $this->me->id, 'body' => 'テストメッセージ']);

    $response = $this->withToken($this->token)->getJson("/api/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversations.0.is_unread', false);
});

it('【会話一覧】メッセージが無い会話は未読でない', function () {
    /** @var \Tests\TestCase $this */
    makeConversation($this->me, $this->target);

    $response = $this->withToken($this->token)->getJson("/api/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversations.0.is_unread', false);
});

it('【会話一覧】last_message_at の降順で並ぶ', function () {
    /** @var \Tests\TestCase $this */
    $conversationA = makeConversation($this->me, $this->target);
    $anotherUser = User::factory()->create();
    $conversationB = makeConversation($this->me, $anotherUser);
    $conversationB->messages()->create(['sender_id' => $this->me->id, 'body' => 'Bのメッセージ']);
    // Laravelのテスト用ヘルパー アプリが見る現在時刻を指定した秒数だけ未来（または過去）に進める。テスト終了後に自動で戻る。
    $this->travel(1)->second();
    $conversationA->messages()->create(['sender_id' => $this->me->id, 'body' => 'Aのメッセージ']);

    $response = $this->withToken($this->token)->getJson("/api/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversations.0.id', $conversationA->id);
    $response->assertJsonPath('conversations.1.id', $conversationB->id);
});

it('【会話一覧】未認証では取得できない', function () {
    /** @var \Tests\TestCase $this */
    makeConversation($this->me, $this->target);

    $response = $this->getJson("/api/conversations");

    $response->assertStatus(401);
});
