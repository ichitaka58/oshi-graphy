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
    public function index(Diary $diary)
    {

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

        $this->diaryLikeService->likeDiary($request, $diary);

        return response()->json([
            'ok' => true,
            'liked' => true,
            'count' => $diary->likes()->count(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
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
