<?php

namespace App\Http\Controllers;

use App\Models\User;

class BadController
{
    public function index(): void
    {
        $users = User::all();
        foreach ($users as $user) {
            echo $user->posts->count();
            $user->posts()->exists();
        }
    }
}
