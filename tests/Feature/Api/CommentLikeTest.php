<?php

use App\Models\Comment;
use App\Models\Diary;
use App\Models\User;
use App\Models\Artist;
use App\Models\Like;

// it('has api/commentlike page', function () {
//     $response = $this->get('/api/commentlike');

//     $response->assertStatus(200);
// });

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->user = User::factory()->create();
    $this->artist = Artist::factory()->create();
});

it('【API】コメントにいいねが作成できる', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $comment = Comment::factory()->for($diary)->create();
    $token = $this->user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/comments/{$comment->id}/like", ['user_id' => $this->user]);
    $response->assertStatus(201);
    $response->assertJson([
        'ok' => true,
        'liked' => true,
    ]);
    $response->assertJsonStructure(['ok', 'liked', 'count']);
    $this->assertDatabaseHas('likes', [
        'user_id' => $this->user->id,
        'likeable_type' => Comment::class,
        'likeable_id' => $comment->id,
    ]);
});

it('コメントのいいねを取り消す', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $comment = Comment::factory()->for($diary)->create();
    $token = $this->user->createToken('test_token')->plainTextToken;
    Like::factory()->forComment($comment, $this->user)->create();

    $response = $this->withToken($token)->delete("/api/comments/{$comment->id}/like");
    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'liked' => false,
    ]);
    $response->assertJsonStructure(['ok', 'liked', 'count']);

    $this->assertDatabaseMissing('likes', [
        'user_id' => $this->user->id,
        'likeable_type' => Comment::class,
        'likeable_id' => $comment->id,
    ]);
});

it('コメントのいいねユーザー一覧を表示できる', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $comment = Comment::factory()->for($diary)->for($this->user)->create();

    $liker1 = User::factory()->create();
    $liker2 = User::factory()->create();

    Like::factory()->forComment($comment, $liker1)->create();
    Like::factory()->forComment($comment, $liker2)->create();

    $token = $this->user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->get("/api/comments/{$comment->id}/likes");
    
    $response->assertStatus(200);
    $response->assertJsonCount(2, 'likers.data');
    expect($response->json('likers.data.*.name'))->toContain($liker1->name)->toContain($liker2->name);
});
