<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
// use Auth;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'unique:' . User::class],
            // Password::defaults() でアプリ全体のパスワードポリシー（最低文字数・記号など）を一括適用する
            // ポリシーの変更は AppServiceProvider::boot() で行うことで全エンドポイントに自動反映される
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            // パスワードは必ずハッシュ化して保存する（平文保存はセキュリティ上許容されない）
            'password' => Hash::make($validatedData['password']),
        ]);

        // plainTextToken はトークン生成時にのみ取得可能で、DB にはハッシュ値のみ保存される
        // このレスポンス以降は再取得できないため、クライアントは必ず安全な場所に保存する必要がある
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            // ログインは既存ハッシュとの照合のみのため、パスワード強度チェックは不要
            'password' => ['required'],
        ]);

        // only() で email と password だけを抽出し、余分なフィールドが Auth::attempt に渡らないようにする
        $credentials = $request->only('email', 'password');

        // Auth::attempt はDBを検索してパスワードのハッシュ照合まで自動で行う
        // このAPIはSanctumによるトークン認証（ステートレス）のため、セッションは使用しない
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid login details'
            ], 401);
        }

        // Auth::attempt はユーザーモデルを返さないため、トークン発行のために別途取得する
        // attempt 成功時点でメールアドレスの存在は確定しているので firstOrFail で取得する
        $user = User::where('email', $request['email'])->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request) {
        // currentAccessToken() で現在のリクエストに使用されたトークンのみを削除する
        // user()->tokens()->delete() とは異なり、他デバイスのセッションには影響しない
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}
