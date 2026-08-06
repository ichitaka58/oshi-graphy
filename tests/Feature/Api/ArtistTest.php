<?php

use App\Models\Artist;
use App\Models\User;

// アーティスト検索のテスト（管理者権限不要）
it('【検索テスト】一般ユーザーでも名前で検索できる', function () {
    $user = User::factory()->create();
    Artist::factory()->create(['name' => 'テストアーティスト', 'kana' => 'てすとあーてぃすと']);
    Artist::factory()->create(['name' => '別のグループ', 'kana' => 'べつのぐるーぷ']);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/artists/search?q=テスト');

    $response->assertStatus(200);
    $response->assertJsonCount(1);
    $response->assertJsonPath('0.name', 'テストアーティスト');
});

it('【検索テスト】kanaでも部分一致検索できる', function () {
    $user = User::factory()->create();
    Artist::factory()->create(['name' => 'アーティストA', 'kana' => 'あーてぃすとえー']);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/artists/search?q=えー');

    $response->assertStatus(200);
    $response->assertJsonCount(1);
});

// 管理者一覧のテスト
it('【管理者一覧テスト】管理者はアーティスト一覧を取得できる', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Artist::factory()->count(3)->create();
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/admin/artists');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'artists.data');
});

it('【管理者一覧テスト】一般ユーザーは一覧を取得できない', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/admin/artists');

    $response->assertStatus(403);
});

// 管理者作成のテスト
it('【管理者作成テスト】管理者はアーティストを作成できる', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/admin/artists', [
        'name' => '新しいアーティスト',
        'kana' => 'あたらしいあーてぃすと',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('artists', ['name' => '新しいアーティスト']);
});

it('【管理者作成テスト】一般ユーザーは作成できない', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/admin/artists', [
        'name' => '新しいアーティスト',
        'kana' => 'あたらしいあーてぃすと',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('artists', ['name' => '新しいアーティスト']);
});

it('【管理者作成テスト】名前が重複している場合はエラーになる', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Artist::factory()->create(['name' => '既存アーティスト']);
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/admin/artists', [
        'name' => '既存アーティスト',
        'kana' => 'きぞんあーてぃすと',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('【管理者作成テスト】ソフトデリート済みの名前は重複チェック対象外', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $deleted = Artist::factory()->create(['name' => '削除済みアーティスト']);
    $deleted->delete();
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/admin/artists', [
        'name' => '削除済みアーティスト',
        'kana' => 'さくじょずみあーてぃすと',
    ]);

    $response->assertStatus(201);
});

// 管理者詳細のテスト
it('【管理者詳細テスト】管理者は詳細を取得できる', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $artist = Artist::factory()->create();
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/admin/artists/{$artist->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('artist.id', $artist->id);
});

it('【管理者詳細テスト】一般ユーザーは詳細を取得できない', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $artist = Artist::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/admin/artists/{$artist->id}");

    $response->assertStatus(403);
});

// 管理者更新のテスト
it('【管理者更新テスト】管理者はアーティストを更新できる', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $artist = Artist::factory()->create(['name' => '旧名前']);
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson("/api/admin/artists/{$artist->id}", [
        'name' => '新名前',
        'kana' => 'しんなまえ',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('artists', ['id' => $artist->id, 'name' => '新名前']);
});

it('【管理者更新テスト】自分自身の名前と同じ場合はエラーにならない', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $artist = Artist::factory()->create(['name' => '変わらない名前', 'kana' => 'かわらないなまえ']);
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson("/api/admin/artists/{$artist->id}", [
        'name' => '変わらない名前',
        'kana' => 'かわらないなまえ',
    ]);

    $response->assertStatus(200);
});

it('【管理者更新テスト】他のアーティストと名前が重複する場合はエラーになる', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Artist::factory()->create(['name' => '既存アーティスト']);
    $artist = Artist::factory()->create(['name' => '対象アーティスト']);
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson("/api/admin/artists/{$artist->id}", [
        'name' => '既存アーティスト',
        'kana' => 'きぞんあーてぃすと',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('【管理者更新テスト】一般ユーザーは更新できない', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $artist = Artist::factory()->create(['name' => '旧名前']);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson("/api/admin/artists/{$artist->id}", [
        'name' => '新名前',
        'kana' => 'しんなまえ',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseHas('artists', ['id' => $artist->id, 'name' => '旧名前']);
});

// 管理者削除のテスト
it('【管理者削除テスト】管理者はアーティストを削除（ソフトデリート）できる', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $artist = Artist::factory()->create();
    $token = $admin->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/admin/artists/{$artist->id}");

    $response->assertStatus(200);
    $this->assertSoftDeleted('artists', ['id' => $artist->id]);
});

it('【管理者削除テスト】一般ユーザーは削除できない', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $artist = Artist::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson("/api/admin/artists/{$artist->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('artists', ['id' => $artist->id, 'deleted_at' => null]);
});
