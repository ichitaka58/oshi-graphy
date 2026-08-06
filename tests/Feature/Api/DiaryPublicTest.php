<?php

use App\Models\Artist;
use App\Models\Block;
use App\Models\Diary;
use App\Models\User;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->token = $this->me->createToken('test_token')->plainTextToken;
});

// みんなの日記一覧のテスト
it('【一覧テスト】公開日記のみ表示され、非公開日記は表示されない', function () {
    $artist = Artist::factory()->create();
    $owner = User::factory()->create();
    $public = Diary::factory()->for($owner)->for($artist)->create(['is_public' => true]);
    Diary::factory()->for($owner)->for($artist)->create(['is_public' => false]);

    $response = $this->withToken($this->token)->getJson('/api/public-diaries');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'diaries.data');
    $response->assertJsonPath('diaries.data.0.id', $public->id);
});

it('【一覧テスト】ブロックしている・されているユーザーの日記は表示されない', function () {
    $artist = Artist::factory()->create();
    $blockedUser = User::factory()->create();
    $blockingUser = User::factory()->create();
    $visibleUser = User::factory()->create();

    Diary::factory()->for($blockedUser)->for($artist)->create(['is_public' => true]);
    Diary::factory()->for($blockingUser)->for($artist)->create(['is_public' => true]);
    $visible = Diary::factory()->for($visibleUser)->for($artist)->create(['is_public' => true]);

    // 自分がblockedUserをブロックしている
    Block::create(['blocker_id' => $this->me->id, 'blocked_id' => $blockedUser->id]);
    // blockingUserが自分をブロックしている
    Block::create(['blocker_id' => $blockingUser->id, 'blocked_id' => $this->me->id]);

    $response = $this->withToken($this->token)->getJson('/api/public-diaries');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'diaries.data');
    $response->assertJsonPath('diaries.data.0.id', $visible->id);
});

it('【一覧テスト】year・month・artist_idで絞り込みができる', function () {
    $artist = Artist::factory()->create();
    $otherArtist = Artist::factory()->create();
    $owner = User::factory()->create();

    $target = Diary::factory()->for($owner)->for($artist)->create([
        'is_public' => true,
        'happened_on' => '2025-06-01',
    ]);
    Diary::factory()->for($owner)->for($artist)->create([
        'is_public' => true,
        'happened_on' => '2024-06-01',
    ]);
    Diary::factory()->for($owner)->for($otherArtist)->create([
        'is_public' => true,
        'happened_on' => '2025-06-01',
    ]);

    $response = $this->withToken($this->token)->getJson(
        "/api/public-diaries?year=2025&month=6&artist_id={$artist->id}",
    );

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'diaries.data');
    $response->assertJsonPath('diaries.data.0.id', $target->id);
});

// 詳細のテスト
it('【詳細テスト】公開日記の詳細を取得できる', function () {
    $artist = Artist::factory()->create();
    $owner = User::factory()->create();
    $diary = Diary::factory()->for($owner)->for($artist)->create(['is_public' => true]);

    $response = $this->withToken($this->token)->getJson("/api/public-diaries/{$diary->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('diary.id', $diary->id);
});

it('【詳細テスト】他人の非公開日記は取得できない', function () {
    $artist = Artist::factory()->create();
    $owner = User::factory()->create();
    $diary = Diary::factory()->for($owner)->for($artist)->create(['is_public' => false]);

    $response = $this->withToken($this->token)->getJson("/api/public-diaries/{$diary->id}");

    $response->assertStatus(403);
});

it('【詳細テスト】自分の非公開日記もこのエンドポイントでは取得できない', function () {
    $artist = Artist::factory()->create();
    $diary = Diary::factory()->for($this->me)->for($artist)->create(['is_public' => false]);

    $response = $this->withToken($this->token)->getJson("/api/public-diaries/{$diary->id}");

    $response->assertStatus(403);
});

it('【詳細テスト】投稿者にブロックされている場合は公開日記でも取得できない', function () {
    $artist = Artist::factory()->create();
    $owner = User::factory()->create();
    $diary = Diary::factory()->for($owner)->for($artist)->create(['is_public' => true]);
    Block::create(['blocker_id' => $owner->id, 'blocked_id' => $this->me->id]);

    $response = $this->withToken($this->token)->getJson("/api/public-diaries/{$diary->id}");

    $response->assertStatus(403);
});

// ユーザー別日記一覧のテスト
it('【ユーザー別一覧テスト】指定ユーザーの公開日記のみ表示される', function () {
    $artist = Artist::factory()->create();
    $owner = User::factory()->create();
    $public = Diary::factory()->for($owner)->for($artist)->create(['is_public' => true]);
    Diary::factory()->for($owner)->for($artist)->create(['is_public' => false]);

    $response = $this->withToken($this->token)->getJson("/api/public-diaries/users/{$owner->id}");

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'diaries.data');
    $response->assertJsonPath('diaries.data.0.id', $public->id);
});

it('【ユーザー別一覧テスト】日記投稿者にブロックされている場合は取得できない', function () {
    $owner = User::factory()->create();
    Block::create(['blocker_id' => $owner->id, 'blocked_id' => $this->me->id]);

    $response = $this->withToken($this->token)->getJson("/api/public-diaries/users/{$owner->id}");

    $response->assertStatus(403);
});
