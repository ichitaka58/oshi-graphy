<?php

namespace App\Services;

use App\Models\Diary;
use Illuminate\Http\Request;

class DiaryLikeService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function getLikers(Diary $diary)
    {
        $likers = $diary->likers()
            ->orderByPivot('created_at', 'desc') // 中間テーブルlikesのcreated_atで並べる。
            ->paginate(10)
            ->withQueryString();


        return $likers;
    }

    public function likeDiary(Request $request, Diary $diary)
    {
        $diary->likes()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);
    }

    public function unlikeDiary(Request $request, Diary $diary)
    {
        $like = $diary->likes()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($like) {
            $like->delete(); // モデルのdeleteなのでdeleteイベントが発火
        }
    }
}
