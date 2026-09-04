<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiaryRequest;
use App\Http\Requests\UpdateDiaryRequest;
use App\Models\Diary;
use App\Services\DiaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DiaryController extends Controller
{
    // オブジェクトが持つ変数（プロパティ）として宣言。他のメソッドから $this->diaryService で使える
    protected $diaryService;

    // コンストラクタ：クラスのインスタンス生成時に自動で呼ばれる
    // 引数に型（DiaryService）を書くだけで、Laravelが自動で new DiaryService() して渡してくれる（依存性注入）
    public function __construct(DiaryService $diaryService)
    {
        // 引数で受け取った $diaryService をプロパティに保存する
        // $this = このオブジェクト自身を指す特別な変数
        $this->diaryService = $diaryService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        [
            'diaries' => $diaries,
            'years' => $years,
            'artists' => $artists,
            'year' => $year,
            'artist' => $artist,
        ] = $this->diaryService->allDiaries($request);

        return response()->json([
            'diaries' => $diaries,
            'years' => $years,
            'artists' => $artists,
            'year' => $year,
            'artist' => $artist,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiaryRequest $request)
    {
        Gate::authorize('create', Diary::class);

        $diary = $this->diaryService->createDiary($request);

        if ($request->hasFile('images')) {
            $this->diaryService->attachImages($diary, $request->file('images'));
        }

        return response()->json([
            'diary' => $diary,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Diary $diary)
    {
        Gate::authorize('view', $diary);

        [
            'diary' => $diary,
            'comments' => $comments,
        ] = $this->diaryService->showDiary($diary);

        return response()->json([
            'diary' => $diary,
            'comments' => $comments,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiaryRequest $request, Diary $diary)
    {
        Gate::authorize('update', $diary);

        $this->diaryService->updateDiary($request, $diary);

        $this->diaryService->deleteImages($request, $diary);

        if ($request->hasFile('images')) {

            $this->diaryService->attachImages($diary, $request->file('images'));
        }

        return response()->json([
            'diary' => $diary,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Diary $diary)
    {
        Gate::authorize('delete', $diary);

        $this->diaryService->deleteDiary($diary);

        return response()->noContent(); // ボディなし
    }
}
