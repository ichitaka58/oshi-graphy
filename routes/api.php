<?php

use App\Http\Controllers\Api\ArtistController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommentLikeController;
use App\Http\Controllers\Api\DiaryController;
use App\Http\Controllers\Api\DiaryLikeController;
use App\Http\Controllers\Api\DiaryPublicController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('diaries', DiaryController::class);
    Route::get('/public-diaries', [DiaryPublicController::class, 'index']);
    Route::get('/public-diaries/{diary}', [DiaryPublicController::class, 'show']);
    Route::get('/public-diaries/users/{user}', [DiaryPublicController::class, 'user']);

    Route::get('/diaries/{diary}/likes', [DiaryLikeController::class, 'index']);
    Route::post('/diaries/{diary}/like', [DiaryLikeController::class, 'store']);
    Route::delete('/diaries/{diary}/like', [DiaryLikeController::class, 'destroy']);

    Route::post('/diaries/{diary}/comments', [CommentController::class, 'store'])->middleware('throttle:20,1'); // 簡易スパム対策（1分20件）
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    Route::post('/diaries/{diary}/comments/reply', [CommentController::class, 'reply'])->middleware('throttle:20,1'); // 簡易スパム対策（1分20件）
    Route::delete('/replies/{comment}', [CommentController::class, 'destroy']);

    Route::post('/comments/{comment}/like', [CommentLikeController::class, 'store']);
    Route::delete('/comments/{comment}/like', [CommentLikeController::class, 'destroy']);
    Route::get('/comments/{comment}/likes', [CommentLikeController::class, 'CommentLikers']);

    Route::get('/artists/search', [ArtistController::class, 'search']);

    // whereNumber: 数字に限定する、それ以外はルーティング層で404にできる。
    Route::get('/users/{user}', [UserProfileController::class, 'show'])->whereNumber('user');

});

