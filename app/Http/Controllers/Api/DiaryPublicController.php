<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Comment;
use App\Models\Diary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DiaryPublicController extends Controller
{
    public function index(Request $request)
    {

        $year = $request->integer('year');
        $month = $request->integer('month');
        $artistId = $request->integer('artist_id');

        // 自分をブロックしているユーザーのidを配列で取得
        $blockedByUserIds = $request->user()->blockedBy()->pluck('users.id');
        // 自分がブロックしているユーザーのidを配列で取得
        $blockingUserIds = $request->user()->blocks()->pluck('users.id');

        $diaries =  Diary::query()
            ->where('is_public', true)
            ->whereNotIn('user_id', $blockedByUserIds) // ブロックされているユーザーの日記を除外
            ->whereNotIn('user_id', $blockingUserIds) // ブロックしているユーザーの日記を除外 
            ->when($year, fn($q) => $q->whereYear('happened_on', $year))
            ->when($month, fn($q) => $q->whereMonth('happened_on', $month))
            ->when($artistId, fn($q) => $q->where('artist_id', $artistId))
            ->with(['artist', 'coverImage', 'user'])
            ->withCount(['comments', 'likes'])
            ->withExists(['likes as liked_by_me' => fn($q) => $q->where('user_id', auth()->id())])
            ->orderBy('happened_on', 'desc')
            ->orderBy('updated_at', 'desc')
            ->paginate(12)
            ->withQueryString();

        $minDate = Diary::where('is_public', true)->min('happened_on');
        $minYear = $minDate ? Carbon::parse($minDate)->year : 2021;
        $years = range(now()->year, $minYear);
        $months = range(1, 12);

        $artistName = $artistId ? Artist::find($artistId)->name : null;

        // return view('public_diaries.index', compact('diaries', 'years', 'months', 'year', 'month', 'artistId', 'artistName'));
        return response()->json([
            'diaries' => $diaries,
            'years' => $years,
            'months' => $months,
            'year' => $year,
            'month' => $month,
            'artistId' => $artistId,
            'artistName' => $artistName,
        ]);
    }

    public function show(Diary $diary)
    {
        abort_unless($diary->is_public, 403); // 公開フラグがなければ403
        // ログインユーザーが日記のユーザーからブロックされていれば、日記にアクセス不可。
        if ($diary->user->isBlocking(auth()->user())) {
            abort(403);
        }

        $diary->load(['user'])
            ->loadCount(['likes', 'comments'])
            ->loadExists(['likes as liked_by_me' => fn($q) => $q->where('user_id', auth()->id())]);

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

        // return view('public_diaries.show', compact('diary', 'comments'));
        return response()->json([
            'diary' => $diary,
            'comments' => $comments,
        ]);
    }

    public function user(User $user, Request $request)
    {
        // ログインユーザーが日記のユーザーからブロックされていれば、日記にアクセス不可。
        if ($user->isBlocking($request->user())) {
            abort(403);
        }

        $year = $request->integer('year');
        $artist = $request->integer('artist');

        $diaries = Diary::query()
            ->where('is_public', true)
            ->where('user_id', $user->id)
            ->when($year, fn($q) => $q->whereYear('happened_on', $year))
            ->when($artist, fn($q) => $q->where('artist_id', $artist))
            ->with(['artist', 'coverImage', 'user'])
            ->withCount(['comments', 'likes'])
            ->withExists(['likes as liked_by_me' => fn($q) => $q->where('user_id', auth()->id())])
            ->orderBy('happened_on', 'desc')
            ->orderBy('updated_at', 'desc')
            ->paginate(6)
            ->withQueryString();

        $minDate = Diary::min('happened_on');
        // 日付を扱う時はCarbonが便利
        $minYear = $minDate ? Carbon::parse($minDate)->year : 2021;
        $years = range(now()->year, $minYear);

        $artists = Artist::whereIn('id', function ($q)  use ($user) {
            $q->select('artist_id')
                ->from('diaries') // diariesからartist_idの一覧を取り出す
                ->where('user_id', $user->id) // そのユーザーが書いた日記に限定
                ->whereNotNull('artist_id');
        })->orderBy('name')
            ->get(['id', 'name']);

        // return view('public_diaries.user', compact('diaries', 'years', 'year', 'artists', 'artist', 'user'));

        return response()->json([
            'diaries' => $diaries,
            'years' => $years,
            'year' => $year,
            'artists' => $artists,
            'artist' => $artist,
            'user' => $user,
        ]);
    }
}
