<?php

use App\Http\Controllers\AiDiaryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryPublicController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryLikeController;
use App\Http\Controllers\CommentLikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserBlockController;
use App\Http\Controllers\UserFollowController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/users/{user}',[UserProfileController::class, 'show'])
        ->whereNumber('user')->name('user.profile.show');
    Route::get('/user_profile/edit',[UserProfileController::class, 'edit'])->name('user.profile.edit');
    Route::patch('/user_profile',[UserProfileController::class, 'update'])->name('user.profile.update');

    Route::resource('diaries', DiaryController::class);
    Route::get('/public_diaries', [DiaryPublicController::class, 'index'])->name('public.diaries.index');
    Route::get('/public_diaries/{diary}', [DiaryPublicController::class, 'show'])->name('public.diaries.show');
    Route::get('/public_diaries/users/{user}',[DiaryPublicController::class, 'user'])->whereNumber('user')->name('public.diaries.user');
    Route::get('/artists/search',[ArtistController::class, 'search'])->name('artists.search');

    Route::post('/diaries/{diary}/comments', [CommentController::class, 'store'])->name('comments.store')
        ->middleware('throttle:20,1'); // 簡易スパム対策（1分20件）
    Route::delete('/comments/{comment}',[CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/diaries/{diary}/comments/reply', [CommentController::class, 'reply'])->name('comments.reply')
        ->middleware('throttle:20,1'); // 簡易スパム対策（1分20件）
    Route::delete('/replies/{comment}', [CommentController::class, 'destroy'])->name('replies.destroy');

    Route::post('/ai/diary-suggest', [AiDiaryController::class, 'suggest'])->name('ai.diary.suggest')->middleware('throttle:10,1,ai-suggest');
    Route::post('/ai/diary-reset', [AiDiaryController::class, 'reset'])->name('ai.diary.reset');

    Route::post('/diaries/{diary}/like', [DiaryLikeController::class, 'store'])->name('diaries.like.store');
    Route::delete('/diaries/{diary}/like', [DiaryLikeController::class, 'destroy'])->name('diaries.like.destroy');
    Route::get('/diaries/{diary}/likes', [DiaryLikeController::class, 'index'])->name('diaries.likes.index');

    Route::post('/comments/{comment}/like', [CommentLikeController::class, 'store'])->name('comments.like.store');
    Route::delete('/comments/{comment}/like', [CommentLikeController::class, 'destroy'])->name('comments.like.destroy');
    Route::get('/comments/{comment}/likes', [CommentLikeController::class, 'commentLikers'])->name('comments.likes.index');

    Route::post('/users/{user}/follow', [UserFollowController::class, 'store'])->whereNumber('user')->name('users.follow.store');
    Route::delete('/users/{user}/follow', [UserFollowController::class, 'destroy'])->whereNumber('user')->name('users.follow.destroy');
    Route::get('/user_follow/followers', [UserFollowController::class, 'followers'])->name('user.follow.followers');
    Route::get('/user_follow/followings', [UserFollowController::class, 'followings'])->name('user.follow.followings');

    Route::post('/users/{user}/block', [UserBlockController::class, 'store'])->whereNumber('user')->name('users.block.store');
    Route::delete('/users/{user}/block', [UserBlockController::class, 'destroy'])->whereNumber('user')->name('users.block.destroy');
    Route::get('/user_block/blocks', [UserBlockController::class, 'blocks'])->name('user.block.blocks');
    Route::delete('/blocks/bulk-destroy', [UserBlockController::class, 'bulkDestroy'])->name('blocks.bulk-destroy');
});

Route::prefix('notifications')->middleware('auth')->group(function(){
    Route::get('/',[NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/mark-all-read',[NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('/{id}/read',[NotificationController::class, 'markRead'])->name('notifications.markRead');
    Route::delete('/{id}',[NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/unread-Count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');
});

Route::get('/notifications/{id}/go',function(\Illuminate\Http\Request $request, string $id){
    $n = $request->user()->notifications()->findOrFail($id);
    if(is_null($n->read_at)) $n->markAsRead();
    $url = data_get($n->data, 'url', route('dashboard'));
    return redirect()->to($url);
})->middleware('auth')->name('notifications.go');

Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function(){
    Route::resource('artists', ArtistController::class)->except(['show']);
    // 他に管理者限定のものがあればここへ
});


require __DIR__.'/auth.php';
