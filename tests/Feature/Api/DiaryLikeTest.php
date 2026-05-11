<?php
use App\Models\Artist;
use App\Models\Diary;
use App\Models\Like;
use App\Models\User;

beforeEach(function () {
    $this->artist = Artist::factory()->create();
    $this->owner = User::factory()->create();
    $this->user = User::factory()->create();
});

it('いいねが作成される', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $token = $this->user->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->postJson("/api/diaries/{$diary->id}/like", [
        'user_id' => $this->user->id,
    ]);
    $response->assertStatus(201);
    $response->assertJson([
        'ok' => true,
        'liked' => true,
    ]);
    $response->assertJsonStructure(['ok', 'liked', 'count']);
    $this->assertDatabaseHas('likes', [
        'user_id' => $this->user->id,
        'likeable_type' => Diary::class,
        'likeable_id' => $diary->id,
    ]);
});


it('いいねを取り消す', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);
    $token = $this->user->createToken('test_token')->plainTextToken;

    Like::factory()->forDiary($diary, $this->user)->create([]);

    $response = $this->withToken($token)->deleteJson("/api/diaries/{$diary->id}/like", ['user_id' => $this->user->id]);
    $response->assertStatus(200);
    $response->assertJson([
        'ok' => true,
        'liked' => false,
    ]);
    $response->assertJsonStructure(['ok', 'liked', 'count']);
    $this->assertDatabaseMissing('likes', [
        'user_id' => $this->user->id,
        'likeable_type' => Diary::class,
        'likeable_id' => $diary->id,
    ]);
});

it('日記のいいねユーザー一覧を表示できる', function () {
    $diary = Diary::factory()->for($this->owner)->for($this->artist)->create(['is_public' => true]);

    $liker1 = User::factory()->create();
    $liker2 = User::factory()->create();

    Like::factory()->forDiary($diary, $liker1)->create([]);
    Like::factory()->forDiary($diary, $liker2)->create([]);

    $token = $this->owner->createToken('test_token')->plainTextToken;

    $response = $this->withToken($token)->get("/api/diaries/{$diary->id}/likes");

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'likers.data');
    expect($response->json('likers.data.*.id'))
        ->toContain($liker1->id)
        ->toContain($liker2->id);
});
