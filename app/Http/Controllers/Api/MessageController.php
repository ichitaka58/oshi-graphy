<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;


class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation)
    {
        Gate::authorize('view', $conversation);

        $otherUser = $conversation->otherUser($request->user());
        Gate::authorize('message', $otherUser);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000']
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return response()->json([
            'message' =>  $message,
        ], 201);
    }
}
