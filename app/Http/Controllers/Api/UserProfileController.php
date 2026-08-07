<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;



class UserProfileController extends Controller
{
    /**
     * Display the user's profile information.
     */
    public function show(Request $request, User $user)
    {
        Gate::authorize('view', $user);
        $user->loadCount([
            'diaries as public_diaries_count' => fn($q) => $q->where('is_public', true),
            'followings',
            'followers'
        ]);
        $isFollowing = $request->user()->isFollowing($user);
        $isBlocking = $request->user()->isBlocking($user);

        return response()->json([
            'user' => $user,
            'is_following' => $isFollowing,
            'is_blocking' => $isBlocking,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $deleteIcon = $request->boolean('delete_icon');

        $data = $request->validateWithBag('profile', [
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'profile' => 'nullable|string|max:1000'
        ]);

        $user->name = $data['name'];

        if ($deleteIcon) {
            if ($user->icon_path) {
                Storage::disk(config('filesystems.media_disk'))->delete($user->icon_path);
            }
            $user->icon_path = null;
        } elseif ($request->hasFile('icon')) {
            // 古いファイルを削除
            if ($user->icon_path) {
                Storage::disk(config('filesystems.media_disk'))->delete($user->icon_path);
            }
            // 新しいファイル名を生成
            $ext = strtolower($request->file('icon')->getClientOriginalExtension());
            $filename = $user->id . '_' . now()->format('YmdHis') . '.' . $ext;
            // 新しいファイルを保存
            $path = $request->file('icon')->storeAs('profile_icons', $filename, config('filesystems.media_disk'));
            // DBにパスを保存
            $user->icon_path = $path;
        }

        // プロフィール文（空文字→nullでクリア）
        if ($request->has('profile')) {
            $user->profile = ($data['profile'] === '') ? null : $data['profile'];
        }

        $user->save();

        return response()->json([
            'user' => $user,
        ]);
    }
}
