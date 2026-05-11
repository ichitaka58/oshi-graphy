<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Services\DiaryLikeService;
use Illuminate\Http\Request;


class DiaryLikeController extends Controller
{
    protected $diaryLikeService;

    public function __construct(DiaryLikeService $diaryLikeService)
    {
        $this->diaryLikeService = $diaryLikeService;
    }

    /**
     * いいねしたユーザーの一覧を生成
     */
    public function index(Diary $diary)
    {
        $likers = $this->diaryLikeService->getLikers($diary);

        return view('diaries.likes.index', compact('diary', 'likers'));
    }

    /**
     * いいね情報を保存、通知を作成
     */
    public function store(Request $request, Diary $diary)
    {
        $this->diaryLikeService->likeDiary($request, $diary);


        return response()->json([
            'ok' => true,
            'liked' => true,
            'count' => $diary->likes()->count(),
        ]);
    }



    public function destroy(Request $request, Diary $diary)
    {
        $this->diaryLikeService->unlikeDiary($request, $diary);

        return response()->json([
            'ok' => true,
            'liked' => false,
            'count' => $diary->likes()->count(),
        ]);
    }
}
