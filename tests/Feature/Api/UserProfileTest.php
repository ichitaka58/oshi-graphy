<?php

use App\Models\Artist;
use App\Models\Block;
use App\Models\Diary;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// プロフィール表示のテスト
it('【プロフィール表示テスト】他人のプロフィールを閲覧でき、公開日記数・フォロー数が含まれる', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    $artist = Artist::factory()->create();
    Diary::factory()->for($target)->for($artist)->create(['is_public' => true]);
    Diary::factory()->for($target)->for($artist)->create(['is_public' => false]);
    Follow::create(['follower_id' => $me->id, 'followed_id' => $target->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/users/{$target->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('user.id', $target->id);
    $response->assertJsonPath('user.public_diaries_count', 1);
    $response->assertJsonPath('is_following', true);
    $response->assertJsonPath('is_blocking', false);
});

it('【プロフィール表示テスト】相手にブロックされている場合は閲覧できない', function () {
    $me = User::factory()->create();
    $target = User::factory()->create();
    Block::create(['blocker_id' => $target->id, 'blocked_id' => $me->id]);
    $token = $me->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson("/api/users/{$target->id}");

    $response->assertStatus(403);
});

// プロフィール更新のテスト
it('【プロフィール更新テスト】名前とプロフィール文を更新できる', function () {
    $user = User::factory()->create(['name' => '旧名前']);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/user_profile', [
        'name' => '新しい名前',
        'profile' => 'よろしくお願いします',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => '新しい名前',
        'profile' => 'よろしくお願いします',
    ]);
});

it('【プロフィール更新テスト】アイコン画像をアップロードできる', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    $file = UploadedFile::fake()->image('icon.png');

    $response = $this->withToken($token)->putJson('/api/user_profile', [
        'name' => $user->name,
        'icon' => $file,
    ]);

    $response->assertStatus(200);
    $user->refresh();
    expect($user->icon_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->icon_path);
});

it('【プロフィール更新テスト】delete_iconを指定するとアイコンが削除される', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile_icons/old.png', 'dummy');
    $user = User::factory()->create(['icon_path' => 'profile_icons/old.png']);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/user_profile', [
        'name' => $user->name,
        'delete_icon' => true,
    ]);

    $response->assertStatus(200);
    $user->refresh();
    expect($user->icon_path)->toBeNull();
    Storage::disk('public')->assertMissing('profile_icons/old.png');
});

it('【プロフィール更新テスト】profileを空文字にするとnullになる', function () {
    $user = User::factory()->create(['profile' => '元のプロフィール']);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/user_profile', [
        'name' => $user->name,
        'profile' => '',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'profile' => null]);
});

it('【プロフィール更新テスト】名前が空の場合はエラーになる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/user_profile', [
        'name' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});
