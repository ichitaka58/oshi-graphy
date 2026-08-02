<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ArtistController extends Controller
{
    public function search(Request $request)
    {
        $artists = Artist::where('name', 'LIKE', "%{$request->q}%")
            ->orWhere('kana', 'LIKE', "%{$request->q}%")
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($artists);
    }

    public function index()
    {
        Gate::authorize('viewAny', Artist::class);

        $artists = Artist::orderBy('kana')->paginate(20);

        return response()->json([
            "artists" => $artists,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Artist::class);

        // 削除済み（ソフトデリート）データは重複チェックから除外
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('artists', 'name')->whereNull('deleted_at'),
            ],
            'kana' => 'required|string|max:100',
        ]);

        Artist::create($data);

        return response()->json([
            'message' => 'Artist created successfully'
        ], 201);
    }

    public function show(Artist $artist)
    {
        Gate::authorize('view', $artist);

        return response()->json([
            'artist' => $artist,
        ]);
    }

    public function update(Request $request, Artist $artist)
    {
        Gate::authorize('update', $artist);

        // 自分自身と削除済み（ソフトデリート）データは重複チェックから除外
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('artists', 'name')
                    ->ignore($artist->id)
                    ->whereNull('deleted_at'),
            ],
            'kana' => 'required|string|max:100',
        ]);

        $artist->update($data);

        return response()->json([
            'message' => 'Artist updated successfully',
        ]);
    }

    public function destroy(Artist $artist)
    {
        Gate::authorize('delete', $artist);

        $artist->delete();

        return response()->json([
            'message' => 'Artist deleted successfully'
        ]);
    }
}
