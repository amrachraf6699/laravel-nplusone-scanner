<?php

namespace App\Http\Controllers;

use App\Models\User;

class CallbackController
{
    public function index(): void
    {
        User::all()->map(fn ($user) => $user->posts->count());
    }
}
