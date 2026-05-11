<?php

namespace App\Http\Controllers;

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
        ]  = $this->diaryService->allDiaries($request);

        return view('diaries.index', compact('diaries', 'years', 'artists', 'year', 'artist'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('diaries.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiaryRequest $request)
    {
        $diary = $this->diaryService->createDiary($request);

        if ($request->hasFile('images')) {
 
            $this->diaryService->attachImages($diary, $request->file('images'));
        }
        return redirect()
            ->route('diaries.index')
            ->with('status', '日記を保存しました')->with('status_type', 'success');
        // セッションに一時的なデータ（フラッシュデータ）を保存するメソッド
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

        return view('diaries.show', compact('diary', 'comments'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Diary $diary)
    {
        Gate::authorize('update', $diary);

        return view('diaries.edit', compact('diary'));
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

        return redirect()
            ->route('diaries.show', $diary)
            ->with('status', '日記を更新しました')->with('status_type', 'success');
        // セッションに一時的なデータ（フラッシュデータ）を保存するメソッド
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Diary $diary)
    {
        Gate::authorize('delete', $diary);

        $this->diaryService->deleteDiary($diary);


        return redirect()
            ->route('diaries.index')
            ->with('status', '日記を削除しました。')
            ->with('status_type', 'success');
    }
}
