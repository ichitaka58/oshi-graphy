<?php
use App\Models\User;
use App\Models\Diary;
use App\Models\Artist;
use App\Models\Comment;


beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->user = User::factory()->create();
    $this->artist = Artist::factory()->create();
});

it('【API】公開日記にコメント投稿できる', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $token = $this->user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/diaries/{$diary->id}/comments", ['body' => 'テストコメント']);

    $response->assertStatus(201);
    $this->assertDatabaseHas('comments', [
        'user_id' => $this->user->id,
        'diary_id' => $diary->id,
        'body' => 'テストコメント',
    ]);
});

it('【API】非公開日記に他人はコメントできない', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => false]);

    $token = $this->user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/diaries/{$diary->id}/comments", ['body' => 'テストコメント']);
    $response->assertStatus(403);
    $this->assertDatabaseMissing('comments', [
        'user_id' => $this->user->id,
        'diary_id' => $diary->id,
        'body' => 'テストコメント',
    ]);
});

it('【API】本人のみが見られる日記詳細画面でコメント一覧が表示される', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $token = $this->owner->createToken('test_token')->plainTextToken;

    $comment = Comment::factory()->for($diary)->for($this->user)->create();

    $response = $this->withToken($token)->getJson("/api/diaries/{$diary->id}");
    $response->assertStatus(200);
    $response->assertJsonPath('comments.0.id', $comment->id);
});

it('【API】ログインユーザーが見られるみんなの日記の詳細画面でコメント一覧が表示される', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $viewer = User::factory()->create();
    $token = $viewer->createToken('test_token')->plainTextToken;
    $comment = Comment::factory()->for($diary)->for($this->user)->create();

    $response = $this->withToken($token)->getJson("/api/public-diaries/{$diary->id}");
    $response->assertStatus(200);
    $response->assertJsonPath('comments.0.id', $comment->id);
    $response->assertJsonPath('comments.0.user_id', $this->user->id);
});

it('【API】自分のコメントを削除できる', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $token = $this->user->createToken('test_token')->plainTextToken;
    $comment = Comment::factory()->for($diary)->for($this->user)->create();
    $isReply = $comment->parent_id !== null;

    $response = $this->withToken($token)->deleteJson("/api/comments/{$comment->id}");
    $response->assertStatus(200);
    $response->assertJson(['isReply' => $isReply]);
    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('【API】他人のコメントは削除できない', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $comment = Comment::factory()->for($diary)->for($this->user)->create();
    $viewer = User::factory()->create();
    $token = $viewer->createToken('test_token')->plainTextToken;
    $response = $this->withToken($token)->deleteJson("/api/comments/{$comment->id}");
    $response->assertStatus(403);
    $this->assertDatabaseHas('comments', ['id' => $comment->id,]);
});

it('【API】コメントに返信投稿ができる', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $comment = Comment::factory()->for($diary)->for($this->user)->create();
    $token = $this->owner->createToken('test_token')->plainTextToken;

    $payload = [
        'body' => 'テストリプライコメント',
        'parent_id' => $comment->id,
    ];
    $response = $this->withToken($token)->postJson("/api/diaries/{$diary->id}/comments/reply", $payload);

    $response->assertStatus(201);
    $this->assertDatabaseHas('comments', [
        'user_id' => $this->owner->id,
        'diary_id' => $diary->id,
        'body' => 'テストリプライコメント',
        'parent_id' => $comment->id,
        'depth' => 1,
        'root_id' => $comment->id,
    ]);
});