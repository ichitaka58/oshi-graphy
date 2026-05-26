<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\Comment;
use App\Models\Diary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DiaryService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function allDiaries(Request $request)
    {
        $user = $request->user();

        $year = $request->integer('year');
        $artist = $request->integer('artist');

        $diaries = $user->diaries()
            ->with(['artist', 'coverImage'])
            // when:$yearがあれば、関数を実行。if($year)と同じ
            ->when($year, fn($q) => $q->whereYear('happened_on', $year))
            ->when($artist, fn($q) => $q->where('artist_id', $artist))
            ->withCount(['comments', 'likes']) // コメント数、いいね数
            ->withExists(['likes as liked_by_me' => fn($q) => $q->where('user_id', auth()->id())])
            ->orderBy('happened_on', 'desc') // まず日付の新しい順
            ->orderBy('updated_at', 'desc') // 同じ日付の中で更新の新しい順
            ->paginate(6) // ページネーション付きで取得
            ->withQueryString(); // 次のページにも検索条件を引き継ぐ

        $minDate = Diary::min('happened_on');
        // 日付を扱う時はCarbonが便利
        $minYear = $minDate ? Carbon::parse($minDate)->year : 2021;
        $years = range(now()->year, $minYear);
        // artist_tableからidがdiaries.artist_idに一致するものに絞り込む
        $artists = Artist::whereIn('id', function ($q)  use ($user) {
            $q->select('artist_id')
                ->from('diaries') // diariesからartist_idの一覧を取り出す
                ->where('user_id', $user->id) // そのユーザーが書いた日記に限定
                ->whereNotNull('artist_id');
        })->orderBy('name')
            ->get(['id', 'name']);

        return [
            'diaries' => $diaries,
            'years' => $years,
            'artists' => $artists,
            'year' => $year,
            'artist' => $artist,
        ];
    }

    public function createDiary(Request $request)
    {
        // FormRequestがコントローラー到達前にバリデーションを実行済み。validated()で合格データを取り出すだけ
        $validated = $request->validated();
        $diary = $request->user()->diaries()->create([
            'happened_on' => $validated['happened_on'],
            'artist_id' => $validated['artist_id'],
            'body' => $validated['body'],
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return $diary;
    }

    // 画像ファイルにカスタムパスを設定し、保存
    public function attachImages(Diary $diary, array $imageFiles): void
    {
        foreach ($imageFiles as $imageFile) {
            $ext = strtolower($imageFile->getClientOriginalExtension());
            // diary_id＋日時＋uniqidの組み合わせでファイル名の衝突を防ぐ
            $filename = $diary->id . '_' . now()->format('YmdHis') . '_' . uniqid() . '.' . $ext;
            $path = $imageFile->storeAs('diary_images', $filename, 'public');
            // storage/app/public/diary_imagesに保存
            // storeは毎回ユニークなファイル名（ハッシュ由来+拡張子）を自動生成
            $diary->images()->create(['path' => $path]);
        }
    }

    public function showDiary(Diary $diary)
    {
        $diary->load(['user', 'artist', 'images'])
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

        return [
            'diary' => $diary,
            'comments' => $comments,
        ];
    }

    public function updateDiary(Request $request, Diary $diary): void
    {
        // PHPのオブジェクトは参照ハンドルで渡されるため、update()の変更は呼び出し元にも反映される（return不要）
        $validated = $request->validated();

        $diary->update([
            'happened_on' => $validated['happened_on'],
            'artist_id' => $validated['artist_id'],
            'body' => $validated['body'],
            'is_public' => $validated['is_public'],
        ]);

    }

    public function deleteImages(Request $request, Diary $diary)
    {
        // delete_imagesはnullable。未送信時はデフォルトの空配列を使い、if文でスキップする
        $deleteIds = $request->input('delete_images', []);
        if (!empty($deleteIds)) {
            $images = $diary->images()->whereIn('id', $deleteIds)->get();
            foreach ($images as $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }
        }
    }

    public function deleteDiary(Diary $diary)
    {
        // $diary->delete()より前にパスを取得する。削除後はimages()リレーションが参照できなくなるため
        // all()はCollectionを素のPHP配列に変換する
        $paths = $diary->images()->pluck('path')->all();
        // 親レコードを削除。diary_imagesはCASCADE設定によりDBから自動削除される
        $diary->delete();

        try {
            Storage::disk('public')->delete($paths); // ファイルの物理削除
        } catch (Throwable $e) { // 何かしらのエラーが起きた時だけ実行、例外＆エラーを開発者向けに表示
            Log::warning('Failed deleting diary image files', [
                'paths' => $paths,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
