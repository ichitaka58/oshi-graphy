<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Display a listing of the resource.
     */
    public function index(Request $request, Diary $diary)
    {
        // 日記オーナーとログインユーザーが同じでなければ、403を返す
        // abort_unless($diary->user_id === $request->user()->id, 403);
        $likers = $this->diaryLikeService->getLikers($diary);

        return response()->json([
            'diary' => $diary,
            'likers' => $likers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Diary $diary)
    {
        // 公開日記としてそのユーザーにいいねを許可されていなければ403を投げる
        abort_unless($diary->isVisibleTo($request->user()), 403);

        $this->diaryLikeService->likeDiary($request, $diary);

        return response()->json([
            'ok' => true,
            'liked' => true,
            'count' => $diary->likes()->count(),
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Diary $diary)
    {
        abort_unless($diary->isVisibleTo($request->user()), 403);

        $this->diaryLikeService->unlikeDiary($request, $diary);

        return response()->json([
            'ok' => true,
            'liked' => false,
            'count' => $diary->likes()->count(),
        ]);
    }
}
