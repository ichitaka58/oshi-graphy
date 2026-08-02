<?php

use App\Models\Artist;
use App\Models\Diary;
use App\Models\User;

//  一覧表示のテスト
it('【一覧表示テスト】自分の日記一覧が表示され、他人の日記は表示されない', function () {
    $me = User::factory()->create();
    $you = User::factory()->create();

    $artist = Artist::factory()->create();

    $myDiaries = Diary::factory()->for($me)->for($artist)->count(3)->create();
    Diary::factory()->for($you)->for($artist)->count(2)->create();

    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/diaries');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'diaries.data');

    // 返ってきた日記が全て自分のものであることを確認
    $response->assertJsonPath(
        'diaries.data.*.user_id',
        $myDiaries->pluck('user_id')->all()
    );
});

it('【作成テスト】日記が作成できる', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create(['name' => 'テストアーティスト']);
    $payload = [
        'artist_id' => $artist->id,
        'happened_on' => '2026-05-08',
        'body' => '日記作成テスト',
        'is_public' => true,
    ];

    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/diaries', $payload);

    $response->assertStatus(201);
    $this->assertDatabaseHas('diaries', [
        'user_id' => $user->id,
        'artist_id' => $artist->id,
        'happened_on' => '2026-05-08',
        'body' => '日記作成テスト',
        'is_public' => 1,
    ]);
});

// 詳細表示のテスト
it('【詳細表示テスト】自分の日記を取得できる', function () {
    $me = User::factory()->create();
    $artist = Artist::factory()->create();
    $diary = Diary::factory()->for($me)->for($artist)->create();

    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/diaries/{$diary->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('diary.id', $diary->id);
});

it('【詳細表示テスト】他人の日記は取得できない', function () {
    $me = User::factory()->create();
    $you = User::factory()->create();
    $artist = Artist::factory()->create();
    $diary = Diary::factory()->for($you)->for($artist)->create();

    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/diaries/{$diary->id}");

    $response->assertStatus(403);
});

// 更新のテスト
it('【更新テスト】自分の日記を更新できる', function () {
    $me = User::factory()->create();
    $artist = Artist::factory()->create();
    $diary = Diary::factory()->for($me)->for($artist)->create();

    $token = $me->createToken('test_token')->plainTextToken;

    $payload = [
        'happened_on' => '2026-01-01',
        'artist_id' => $artist->id,
        'body' => '更新後の本文',
        'is_public' => true,
    ];

    $response = $this->withToken($token)->putJson("/api/diaries/{$diary->id}", $payload);

    $response->assertStatus(200);
    $this->assertDatabaseHas('diaries', [
        'id' => $diary->id,
        'body' => '更新後の本文',
        'happened_on' => '2026-01-01',
    ]);
});

it('【更新テスト】他人の日記は更新できない', function () {
    $me = User::factory()->create();
    $you = User::factory()->create();
    $artist = Artist::factory()->create();
    $diary = Diary::factory()->for($you)->for($artist)->create();

    $token = $me->createToken('test_token')->plainTextToken;

    $payload = [
        'happened_on' => '2026-01-01',
        'artist_id' => $artist->id,
        'body' => '不正な更新',
        'is_public' => false,
    ];

    $response = $this->withToken($token)->putJson("/api/diaries/{$diary->id}", $payload);

    $response->assertStatus(403);
});

// 削除のテスト
it('【削除テスト】自分の日記を削除できる', function () {
    $me = User::factory()->create();
    $artist = Artist::factory()->create();
    $diary = Diary::factory()->for($me)->for($artist)->create();

    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/diaries/{$diary->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('diaries', ['id' => $diary->id]);
});

it('【削除テスト】他人の日記は削除できない', function () {
    $me = User::factory()->create();
    $you = User::factory()->create();
    $artist = Artist::factory()->create();
    $diary = Diary::factory()->for($you)->for($artist)->create();

    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/diaries/{$diary->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('diaries', ['id' => $diary->id]);
});
