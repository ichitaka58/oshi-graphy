<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information(email).
     * メールアドレスの更新
     */
    public function update(Request $request)
    {
        // バリデーション emailのみ リクエストクラスはnameとemailのため使わず
        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($request->user()->id),
            ]
        ]);
        // $request->user(): Sanctumトークンで認証済みの現在のユーザーモデルを取得
        // fill(..): 取得した値をモデルの属性にまとめてセットする。
        $request->user()->fill($validated);

        // isDirty('email): fill()した結果、email属性の値がDBから読み込んだ元の値と
        // 実際に変わっているかを判定するEloquentのメソッド（変更されていなければfalse）
        if ($request->user()->isDirty('email')) {
            // 変更されていたらemail_verified_atをnullに戻す。
            // これは「メールを変えたら再検証が必要」という意図のコードだが、実際に確認メールを送るロジックは
            // このプロジェクトにはまだ存在しない(MustVerifyEmailが無効化されているため)。
            // なので現状は単にemail_verified_atカラムがnullになるだけ。
            $request->user()->email_verified_at = null;
        }
        // DBにUPDATEクエリが発行される
        $request->user()->save();

        return response()->json([
            'message' => 'Emailを更新しました'
        ]);
    }

    /**
     * アカウントの削除
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password:sanctum'],
        ]);

        $user = $request->user();

        // トークンを削除
        $user->tokens()->delete();
        $user->delete();


        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }
}
