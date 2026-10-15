<?php

namespace App\Http\Controllers;

use App\Models\User;

class GoodController
{
    public function index(): void
    {
        $users = User::with('posts')->get();
        foreach ($users as $user) {
            echo $user->posts->count();
        }
        foreach (User::all() as $user) {
            echo $user->name;
        }
    }
}
