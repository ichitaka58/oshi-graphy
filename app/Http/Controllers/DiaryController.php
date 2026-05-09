<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\Artist;
use App\Models\Comment;
use App\Services\DiaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;
use Throwable;


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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'happened_on' => 'required|date',
            // exists:artists,id→存在しないartist_idが入らないようにする
            'artist_id' => 'required|integer|exists:artists,id',
            'body' => 'required|string',
            'images' => 'nullable|array',
            // images.*とすることで,複数ファイル（配列）をチェック可能
            // image:画像かどうか、mimes:許可する拡張子、max:5120 5MB
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'is_public' => 'boolean'
        ]);

        $diary = $request->user()->diaries()->create([
            'happened_on' => $validated['happened_on'],
            'artist_id' => $validated['artist_id'],
            'body' => $validated['body'],
            'is_public' => $validated['is_public'],
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                $ext = strtolower($imageFile->getClientOriginalExtension());
                $filename = $diary->id . '_' . now()->format('YmdHis') . '_' . uniqid() . '.' . $ext;
                $path = $imageFile->storeAs('diary_images', $filename, 'public');
                // storage/app/public/diary_imagesに保存
                // storeは毎回ユニークなファイル名（ハッシュ由来+拡張子）を自動生成
                $diary->images()->create(['path' => $path]);
            }
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

        $diary->load(['user'])
            ->loadCount(['comments', 'likes'])
            ->loadExists([
                'likes as liked_by_me' => fn($q) => $q->where('user_id', auth()->id()),
            ]);

        // コメントをlikes_count / liked_by_me 付きで取得
        // コメントへの返信を多層化：親コメントは新着順、返信コメントは古い順にソート
        // 親を除く全ての返信数をカウント
        $comments = Comment::query()
            ->leftJoin('comments as r', 'r.id', '=', 'comments.root_id')
            ->where('comments.diary_id', $diary->id)
            ->orderByDesc('r.created_at')
            ->orderBy('comments.path')
            ->select('comments.*')
            ->with('user')
            ->withCount(['replies', 'likes'])
            ->withExists([
                'likes as liked_by_me' => fn($q) => $q->where('user_id', auth()->id()),
            ])
            ->get();

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
    public function update(Request $request, Diary $diary)
    {
        Gate::authorize('update', $diary);

        $validated = $request->validate([
            'happened_on' => 'required|date',
            // exists:artists,id→存在しないartist_idが入らないようにする
            'artist_id' => 'required|integer|exists:artists,id',
            'body' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'is_public' => 'boolean',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'integer|distinct|exists:diary_images,id',
        ]);

        $diary->update([
            'happened_on' => $validated['happened_on'],
            'artist_id' => $validated['artist_id'],
            'body' => $validated['body'],
            'is_public' => $validated['is_public'],
        ]);

        // 画像の物理削除とDBの削除
        $deleteIds = $request->input('delete_images', []);
        if (!empty($deleteIds)) {
            $images = $diary->images()->whereIn('id', $deleteIds)->get();
            foreach ($images as $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                $ext = strtolower($imageFile->getClientOriginalExtension());
                $filename = $diary->id . '_' . now()->format('YmdHis') . '_' . uniqid() . '.' . $ext;
                $path = $imageFile->storeAs('diary_images', $filename, 'public');
                // storage/app/public/diary_imagesに保存
                // storeは毎回ユニークなファイル名（ハッシュ由来+拡張子）を自動生成
                $diary->images()->create(['path' => $path]);
            }
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

        // 画像のパスだけを取り出す。all()は中身を素の配列に変換
        $paths = $diary->images()->pluck('path')->all();
        // 親を削除、画像はCASCADEで自動削除     
        $diary->delete();

        try {
            Storage::disk('public')->delete($paths); // ファイルの物理削除
        } catch (Throwable $e) { // 何かしらのエラーが起きた時だけ実行、例外＆エラーを開発車向けに表示
            Log::warning('Failed deleting diary image files', [
                'paths' => $paths,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('diaries.index')
            ->with('status', '日記を削除しました。')
            ->with('status_type', 'success');
    }
}
