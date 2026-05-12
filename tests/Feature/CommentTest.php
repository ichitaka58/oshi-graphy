<?php
use App\Models\User;
use App\Models\Diary;
use App\Models\Artist;
use App\Models\Comment;

beforeEach(function() {
    $this->owner = User::factory()->create();
    $this->user = User::factory()->create();
    $this->artist = Artist::factory()->create();

});

it('公開日記にコメント投稿できる', function() {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $this->actingAs($this->user);
    $response = $this->post(route('comments.store', $diary), ['body' => 'テストコメント']);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'コメントを投稿しました。');
    $this->assertDatabaseHas('comments', [
        'user_id' => $this->user->id,
        'diary_id' => $diary->id,
        'body' => 'テストコメント',
    ]);
});

it('非公開日記に他人はコメントできない', function() {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => false]);

    $response = $this->actingAs($this->user)->post(route('comments.store', $diary), ['body' => 'テストコメント']);
    $response->assertForbidden();
    $this->assertDatabaseMissing('comments',[
        'user_id' => $this->user->id,
        'diary_id' => $diary->id,
        'body' => 'テストコメント',
    ]);

});

it('本人のみが見られる日記詳細画面でコメント一覧が表示される', function() {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $comment = Comment::factory()->for($diary)->for($this->user)->create();

    $response = $this->actingAs($this->owner)->get(route('diaries.show', $diary));
    $response->assertOk();
    $response->assertSee($comment->body);
    $response->assertSee($this->user->name);

});

it('ログインユーザーが見られるみんなの日記の詳細画面でコメント一覧が表示される',function() {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $viewer = User::factory()->create();
    $comment = Comment::factory()->for($diary)->for($this->user)->create();

    $response = $this->actingAs($viewer)->get(route('public.diaries.show', $diary));
    $response->assertOk();
    $response->assertSee($comment->body);
    $response->assertSee($this->user->name);

});

it('自分のコメントを削除できる', function() {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $comment = Comment::factory()->for($diary)->for($this->user)->create();

    $response = $this->actingAs($this->user)->delete(route('comments.destroy', $comment));
    $response->assertRedirect();
    $response->assertSessionHas('status', 'コメントを削除しました。');
    $this->assertDatabaseMissing('comments',['id' => $comment->id]);
});

it('他人のコメントは削除できない', function() {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $comment = Comment::factory()->for($diary)->for($this->user)->create();
    $viewer = User::factory()->create();
    $response = $this->actingAs($viewer)->delete(route('comments.destroy', $comment));
    $response->assertForbidden();
    $this->assertDatabaseHas('comments', ['id' => $comment->id,]);
});

it('コメントに返信投稿ができる', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $comment = Comment::factory()->for($diary)->for($this->user)->create();

    $payload = [
        'body' => 'テストリプライコメント',
        'parent_id' => $comment->id,
    ];
    $response = $this->actingAs($this->owner)->post(route('comments.reply', $diary), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('status', '返信を投稿しました');

    $this->assertDatabaseHas('comments', [
        'user_id' => $this->owner->id,
        'diary_id' => $diary->id,
        'body' => 'テストリプライコメント',
        'parent_id' => $comment->id,
        'depth' => 1,
        'root_id' => $comment->id,
    ]);
});

