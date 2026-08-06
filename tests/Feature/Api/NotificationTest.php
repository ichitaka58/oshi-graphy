<?php

use App\Models\User;
use App\Notifications\UserFollowedNotification;

function notifyUser(User $target, ?User $actor = null): \Illuminate\Notifications\DatabaseNotification
{
    $actor ??= User::factory()->create();
    $target->notify(new UserFollowedNotification($actor, $target));

    return $target->notifications()->latest()->first();
}

it('【通知一覧テスト】未認証では利用できない', function () {
    $response = $this->getJson('/api/notifications');

    $response->assertStatus(401);
});

it('【通知一覧テスト】自分宛の通知が新しい順に取得できる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    notifyUser($user);
    notifyUser($user);
    // 他人宛の通知は含まれない
    notifyUser(User::factory()->create());

    $response = $this->withToken($token)->getJson('/api/notifications');

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'notifications.data');
});

it('【未読件数テスト】未読の通知件数のみを返す', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    notifyUser($user);
    $read = notifyUser($user);
    $read->markAsRead();

    $response = $this->withToken($token)->getJson('/api/notifications/unread-count');

    $response->assertStatus(200);
    $response->assertJson(['count' => 1]);
});

it('【既読化テスト】未読の通知を既読にできる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    $notification = notifyUser($user);
    expect($notification->read_at)->toBeNull();

    $response = $this->withToken($token)->postJson("/api/notifications/{$notification->id}/read");

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('【既読化テスト】既に既読の通知に対して呼んでもエラーにならない', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    $notification = notifyUser($user);
    $notification->markAsRead();
    $readAt = $notification->fresh()->read_at;

    $response = $this->withToken($token)->postJson("/api/notifications/{$notification->id}/read");

    $response->assertStatus(200);
    // 既読済みの場合は上書きしない
    expect($notification->fresh()->read_at)->toEqual($readAt);
});

it('【既読化テスト】他人の通知は既読にできない', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = $other->createToken('test_token')->plainTextToken;
    $notification = notifyUser($owner);

    $response = $this->withToken($token)->postJson("/api/notifications/{$notification->id}/read");

    $response->assertStatus(404);
});

it('【一括既読化テスト】未読の通知だけがすべて既読になる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    $n1 = notifyUser($user);
    $n2 = notifyUser($user);
    $otherUsersNotification = notifyUser(User::factory()->create());

    $response = $this->withToken($token)->postJson('/api/notifications/mark-all-read');

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
    expect($n1->fresh()->read_at)->not->toBeNull();
    expect($n2->fresh()->read_at)->not->toBeNull();
    expect($otherUsersNotification->fresh()->read_at)->toBeNull();
});

it('【未読化テスト】既読の通知を未読に戻せる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    $notification = notifyUser($user);
    $notification->markAsRead();

    $response = $this->withToken($token)->postJson("/api/notifications/{$notification->id}/mark-unread");

    $response->assertStatus(200);
    expect($notification->fresh()->read_at)->toBeNull();
});

it('【未読化テスト】他人の通知は未読に戻せない', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = $other->createToken('test_token')->plainTextToken;
    $notification = notifyUser($owner);
    $notification->markAsRead();

    $response = $this->withToken($token)->postJson("/api/notifications/{$notification->id}/mark-unread");

    $response->assertStatus(404);
});

it('【削除テスト】自分宛の通知を削除できる', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;
    $notification = notifyUser($user);

    $response = $this->withToken($token)->deleteJson("/api/notifications/{$notification->id}");

    $response->assertStatus(200);
    $response->assertJson(['ok' => true]);
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
});

it('【削除テスト】他人の通知は削除できない', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $token = $other->createToken('test_token')->plainTextToken;
    $notification = notifyUser($owner);

    $response = $this->withToken($token)->deleteJson("/api/notifications/{$notification->id}");

    $response->assertStatus(404);
    $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
});
