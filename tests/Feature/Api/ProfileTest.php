<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// メールアドレス更新のテスト
it('【メール更新テスト】正しいメールアドレスに更新でき、認証状態がリセットされる', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->patchJson('/api/profile', [
        'email' => 'new@example.com',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'new@example.com',
        'email_verified_at' => null,
    ]);
});

it('【メール更新テスト】変更がない場合は認証状態を維持する', function () {
    $verifiedAt = now();
    $user = User::factory()->create([
        'email' => 'same@example.com',
        'email_verified_at' => $verifiedAt,
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->patchJson('/api/profile', [
        'email' => 'same@example.com',
    ]);

    $response->assertStatus(200);
    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
});

it('【メール更新テスト】他人が使用中のメールアドレスには更新できない', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'me@example.com']);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->patchJson('/api/profile', [
        'email' => 'taken@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('【メール更新テスト】不正な形式のメールアドレスはエラーになる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->patchJson('/api/profile', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

// アカウント削除のテスト
it('【アカウント削除テスト】正しいパスワードでアカウントを削除できる', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson('/api/profile', [
        'password' => 'password123',
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

it('【アカウント削除テスト】パスワードが間違っている場合は削除できない', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson('/api/profile', [
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('【アカウント削除テスト】パスワードが空の場合はエラーになる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->deleteJson('/api/profile', []);

    $response->assertStatus(422);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});
