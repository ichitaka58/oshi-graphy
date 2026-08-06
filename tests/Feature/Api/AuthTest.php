<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// 登録のテスト
it('【登録テスト】正しい入力でユーザー登録ができ、トークンが発行される', function () {
    $payload = [
        'name' => 'テスト太郎',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->postJson('/api/register', $payload);

    $response->assertStatus(200);
    $response->assertJsonStructure(['access_token', 'token_type']);
    $response->assertJsonPath('token_type', 'Bearer');

    $this->assertDatabaseHas('users', [
        'name' => 'テスト太郎',
        'email' => 'test@example.com',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

it('【登録テスト】必須項目が空の場合はエラーになる', function () {
    $response = $this->postJson('/api/register', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('【登録テスト】メールアドレスが既に使われている場合はエラーになる', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'テスト太郎',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

it('【登録テスト】パスワードが確認用と一致しない場合はエラーになる', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'テスト太郎',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

it('【登録テスト】パスワードが8文字未満の場合はエラーになる', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'テスト太郎',
        'email' => 'test@example.com',
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

// ログインのテスト
it('【ログインテスト】正しい認証情報でログインでき、トークンが発行される', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['access_token', 'token_type']);
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

it('【ログインテスト】パスワードが間違っている場合は401になる', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
});

it('【ログインテスト】存在しないメールアドレスの場合は401になる', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'notfound@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(401);
});

it('【ログインテスト】必須項目が空の場合はエラーになる', function () {
    $response = $this->postJson('/api/login', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email', 'password']);
});

// ログアウトのテスト
it('【ログアウトテスト】現在のトークンのみが削除される', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('current')->plainTextToken;
    $user->createToken('other_device');

    expect($user->tokens()->count())->toBe(2);

    $response = $this->withToken($currentToken)->postJson('/api/logout');

    $response->assertStatus(200);
    expect($user->tokens()->count())->toBe(1);
});

it('【ログアウトテスト】未認証の場合は401になる', function () {
    $response = $this->postJson('/api/logout');

    $response->assertStatus(401);
});

// 認証済みユーザー取得のテスト
it('【ユーザー情報取得テスト】トークンがあれば自分の情報を取得できる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/user');

    $response->assertStatus(200);
    $response->assertJsonPath('id', $user->id);
    $response->assertJsonPath('email', $user->email);
});

it('【ユーザー情報取得テスト】未認証の場合は401になる', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(401);
});
