<?php

use App\Http\Controllers\Api\AiDiaryController;
use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommentLikeController;
use App\Http\Controllers\Api\DiaryController;
use App\Http\Controllers\Api\DiaryLikeController;
use App\Http\Controllers\Api\DiaryPublicController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserBlockController;
use App\Http\Controllers\Api\UserFollowController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1'); // レート制限: 1分間に5件まで（IPアドレスごと）。超えたら429が返る
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 同上

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('diaries', DiaryController::class);
    Route::get('/public-diaries', [DiaryPublicController::class, 'index']);
    Route::get('/public-diaries/{diary}', [DiaryPublicController::class, 'show']);
    Route::get('/public-diaries/users/{user}', [DiaryPublicController::class, 'user'])->whereNumber('user');

    Route::get('/diaries/{diary}/likes', [DiaryLikeController::class, 'index']);
    Route::post('/diaries/{diary}/like', [DiaryLikeController::class, 'store']);
    Route::delete('/diaries/{diary}/like', [DiaryLikeController::class, 'destroy']);

    Route::post('/diaries/{diary}/comments', [CommentController::class, 'store'])->middleware('throttle:20,1'); // 簡易スパム対策（1分20件、ユーザーごと）
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('/diaries/{diary}/comments/reply', [CommentController::class, 'reply'])->middleware('throttle:20,1'); // 簡易スパム対策（1分20件、ユーザーごと）
    Route::delete('/replies/{comment}', [CommentController::class, 'destroy']);

    Route::post('/comments/{comment}/like', [CommentLikeController::class, 'store']);
    Route::delete('/comments/{comment}/like', [CommentLikeController::class, 'destroy']);
    Route::get('/comments/{comment}/likes', [CommentLikeController::class, 'CommentLikers']);

    Route::get('/artists/search', [ArtistController::class, 'search']);

    // whereNumber: 数字に限定する、それ以外はルーティング層で404にできる。
    Route::get('/users/{user}', [UserProfileController::class, 'show'])->whereNumber('user');
    Route::PUT('/user_profile', [UserProfileController::class, 'update']);

    Route::post('/ai/diary-suggest', [AiDiaryController::class, 'suggest'])->middleware('throttle:10,1,ai-suggest'); // 最後のai-suggestは名札。カウンタをcommentsと分けるため。

    // アカウント設定
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:6,1,account'); // email変更
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('throttle:6,1,account'); // アカウント削除
    Route::put('/password', [PasswordController::class, 'update'])->middleware('throttle:6,1,account'); // パスワード変更

    Route::post('/users/{user}/follow', [UserFollowController::class, 'store'])->whereNumber('user');
    Route::delete('/users/{user}/follow', [UserFollowController::class, 'destroy'])->whereNumber('user');
    Route::get('/users/{user}/followers', [UserFollowController::class, 'followers'])->whereNumber('user');
    Route::get('/users/{user}/followings', [UserFollowController::class, 'followings'])->whereNumber('user');

    Route::post('/users/{user}/block', [UserBlockController::class, 'store'])->whereNumber('user');
    Route::delete('/users/{user}/block', [UserBlockController::class, 'destroy'])->whereNumber('user');
    Route::get('/users/user-blocks', [UserBlockController::class, 'blocks']);
    // Route::delete('/blocks/bulk-destroy', [UserBlockController::class, 'bulkDestroy'])->name('blocks.bulk-destroy');
});

Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/{id}/mark-unread', [NotificationController::class, 'markUnread']);
});

Route::middleware(['auth:sanctum', 'can:access-admin'])->prefix('admin')->group(function () {
    Route::apiResource('artists', ArtistController::class);
    // 他に管理者限定のものがあればここへ
});

