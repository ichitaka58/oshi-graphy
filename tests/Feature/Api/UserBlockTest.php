<?php

use App\Models\Block;
use App\Models\Follow;
use App\Models\User;

// ブロックのテスト
it('【ブロックテスト】他人をブロックできる', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/block");

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'blocking' => true]);
    $this->assertDatabaseHas('blocks', [
        'blocker_id' => $me->id,
        'blocked_id' => $target->id,
    ]);
});

it('【ブロックテスト】既にブロック済みの場合は重複せずOKを返す', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Block::create(['blocker_id' => $me->id, 'blocked_id' => $target->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/block");

    $response->assertStatus(200);
    expect(Block::where('blocker_id', $me->id)->where('blocked_id', $target->id)->count())->toBe(1);
});

it('【ブロックテスト】自分自身はブロックできない', function () {
    $me = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$me->id}/block");

    $response->assertStatus(403);
});

it('【ブロックテスト】ブロックすると相互のフォロー関係が解除される', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    Follow::create(['follower_id' => $target->id, 'followed_id' => $me->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/users/{$target->id}/block");

    $response->assertStatus(200);
    $this->assertDatabaseMissing('follows', ['follower_id' => $me->id, 'followed_id' => $target->id]);
    $this->assertDatabaseMissing('follows', ['follower_id' => $target->id, 'followed_id' => $me->id]);
});

// ブロック解除のテスト
it('【ブロック解除テスト】ブロック中の相手を解除できる', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Block::create(['blocker_id' => $me->id, 'blocked_id' => $target->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/users/{$target->id}/block");

    $response->assertStatus(200);
    $response->assertJson(['ok' => true, 'blocking' => false]);
    $this->assertDatabaseMissing('blocks', [
        'blocker_id' => $me->id,
        'blocked_id' => $target->id,
    ]);
});

it('【ブロック解除テスト】ブロックしていない相手は解除できない', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/users/{$target->id}/block");

    $response->assertStatus(403);
});

it('【ブロック解除テスト】自分自身は解除できない', function () {
    $me = User::factory()->create();
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/users/{$me->id}/block");

    $response->assertStatus(403);
});

// 一覧取得のテスト
it('【ブロック一覧テスト】自分がブロックしているユーザー一覧を取得できる', function () {
    $me = User::factory()->create();
    $blocked = User::factory()->count(2)->create();
    foreach ($blocked as $target) {
        Block::create(['blocker_id' => $me->id, 'blocked_id' => $target->id]);
    }
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/users/user-blocks');

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'blocks.data');
});
