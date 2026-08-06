<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('【パスワード更新テスト】正しい現在のパスワードで新しいパスワードに更新できる', function () {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/password', [
        'current_password' => 'current-password',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertStatus(200);
    $user->refresh();
    expect(Hash::check('new-password123', $user->password))->toBeTrue();
});

it('【パスワード更新テスト】現在のパスワードが間違っている場合はエラーになる', function () {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['current_password']);
    $user->refresh();
    expect(Hash::check('current-password', $user->password))->toBeTrue();
});

it('【パスワード更新テスト】新しいパスワードが確認用と一致しない場合はエラーになる', function () {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/password', [
        'current_password' => 'current-password',
        'password' => 'new-password123',
        'password_confirmation' => 'different123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

it('【パスワード更新テスト】新しいパスワードが8文字未満の場合はエラーになる', function () {
    $user = User::factory()->create([
        'password' => Hash::make('current-password'),
    ]);
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->putJson('/api/password', [
        'current_password' => 'current-password',
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});
