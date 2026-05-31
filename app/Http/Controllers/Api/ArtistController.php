<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;

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
}
