<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;


class UserProfileController extends Controller
{

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('user_profile.edit', compact('user'));
    }

    /**
     * Update the user's profile information.
     */
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

        return redirect()->route('user.profile.show', $user)
            ->with('status', $deleteIcon ? 'アイコンが削除されました。' : 'プロフィールが更新されました。')
            ->with('status_type', 'success');
    }

    /**
     * Display the user's profile information.
     */
    public function show(User $user)
    {
        Gate::authorize('view', $user);
        $user->loadCount([
            'diaries as public_diaries_count' => fn($q) => $q->where('is_public', true)]);

        return view('user_profile.show', compact('user'));
    }
}
