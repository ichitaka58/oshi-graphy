<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;


class UserProfileController extends Controller
{
    /**
     * Display the user's profile information.
     */
    public function show(User $user)
    {
        Gate::authorize('view', $user);
        $user->loadCount([
            'diaries as public_diaries_count' => fn($q) => $q->where('is_public', true)
        ]);
        return response()->json([
            'user' => $user,
        ]);
    }
}
