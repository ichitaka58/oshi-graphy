<?php

use App\Models\Conversation;
use App\Models\Follow;
use App\Models\User;


it('【会話作成】相互フォロー中の相手と会話を作成できる', function () {
    /** @var \Tests\TestCase $this */ //赤い波線を消すおまじない
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
    $me = User::factory()->create();
    $target = User::factory()->create();
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    Follow::create(['follower_id' => $target->id, 'followed_id' => $me->id]);
    $conversation = Conversation::create(['user_one_id' => $me->id, 'user_two_id' => $target->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/conversations");

    $response->assertStatus(200);
    $response->assertJsonPath('conversation.id', $conversation->id);
    $this->assertDatabaseCount('conversations', 1);
});

it('【会話作成】片思い（自分→相手だけフォロー）は作れない', function () {
    /** @var \Tests\TestCase $this */
    $me = User::factory()->create();
    $target = User::factory()->create();
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/conversations");

    $response->assertStatus(403);
    $this->assertDatabaseCount('conversations', 0);
});

it('【会話作成】逆片思い（相手→自分だけフォロー）も作れない', function () {
    /** @var \Tests\TestCase $this */
    $me = User::factory()->create();
    $target = User::factory()->create();
    Follow::create(['follower_id' => $target->id, 'followed_id' => $me->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/conversations");

    $response->assertStatus(403);
    $this->assertDatabaseCount('conversations', 0);
});

it('【会話作成】自分自身とは作れない', function () {
    /** @var \Tests\TestCase $this */
    $me = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$me->id}/conversations");

    $response->assertStatus(403);
    $this->assertDatabaseCount('conversations', 0);
});

it('【会話作成】未認証では作れない', function () {
    /** @var \Tests\TestCase $this */
    $me = User::factory()->create();
    $target = User::factory()->create();

    $response = $this->postJson("/api/users/{$target->id}/conversations");

    $response->assertStatus(401);
    $this->assertDatabaseCount('conversations', 0);
});