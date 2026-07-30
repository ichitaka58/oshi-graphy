<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:artists,name',
            'kana' => 'required|string|max:100'
        ]);

        Artist::create($data);

        return response()->json([
            'message' => 'Artist created successfully'
        ], 201);
    }

    public function update(Request $request, Artist $artist)
    {
        Gate::authorize('update', $artist);

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:artists,name,' . $artist->id, // このidのデータは除く
            'kana' => 'required|string|max:100'
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
