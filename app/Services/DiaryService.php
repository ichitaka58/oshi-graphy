<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\Diary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
}
