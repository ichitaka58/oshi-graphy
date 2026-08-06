<?php

use App\Models\Follow;
use App\Models\User;

// フォローのテスト
it('【フォローテスト】他人をフォローできる', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/follow");

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'following' => true]);
    $this->assertDatabaseHas('follows', [
        'follower_id' => $me->id,
        'followed_id' => $target->id,
    ]);
});

it('【フォローテスト】既にフォロー済みの場合は重複せずOKを返す', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/follow");

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'following' => true]);
    expect(Follow::where('follower_id', $me->id)->where('followed_id', $target->id)->count())->toBe(1);
});

it('【フォローテスト】自分自身はフォローできない', function () {
    $me = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$me->id}/follow");

    $response->assertStatus(403);
});

it('【フォローテスト】ブロックされている相手はフォローできない', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    $target->blocks()->attach($me->id);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/follow");

    $response->assertStatus(403);
    $this->assertDatabaseMissing('follows', [
        'follower_id' => $me->id,
        'followed_id' => $target->id,
    ]);
});

// アンフォローのテスト
it('【アンフォローテスト】フォロー中の相手をアンフォローできる', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/users/{$target->id}/follow");

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'following' => false]);
    $this->assertDatabaseMissing('follows', [
        'follower_id' => $me->id,
        'followed_id' => $target->id,
    ]);
});

it('【アンフォローテスト】フォローしていない相手はアンフォローできない', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/users/{$target->id}/follow");

    $response->assertStatus(403);
});

it('【アンフォローテスト】自分自身はアンフォローできない', function () {
    $me = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/users/{$me->id}/follow");

    $response->assertStatus(403);
});

// 一覧取得のテスト
it('【フォロワー一覧テスト】フォロワー一覧を取得できる', function () {
    $target = User::factory()->create();
    $followers = User::factory()->count(3)->create();
    foreach ($followers as $follower) {
        Follow::create(['follower_id' => $follower->id, 'followed_id' => $target->id]);
    }
    $token = $target->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/users/{$target->id}/followers");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'followers.data');
});

it('【フォロー中一覧テスト】フォロー中のユーザー一覧を取得できる', function () {
    $me = User::factory()->create();
    $followings = User::factory()->count(2)->create();
    foreach ($followings as $target) {
        Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    }
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/users/{$me->id}/followings");

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'followings.data');
});
