<?php

namespace App\Http\Controllers;

use App\Models\User;

class RemediationController
{
    public function indexedLoops(): void
    {
        $users = User::all();
        for ($i = 0; $i < 10; $i++) {
            echo $users[$i]->posts->count();
        }
        while ($i < 20) {
            $users[$i]->posts()->count();
            $i++;
        }
        do {
            $users[$i]->posts->count();
            $i++;
        } while ($i < 30);
    }

    public function safeAggregateAndReassignment(): void
    {
        $users = User::withCount('posts')->get();
        foreach ($users as $user) {
            echo $user->posts_count;
            echo $user->posts->count();
        }

        $users = User::all();
        $users = 'reassigned';
        foreach ($users as $user) {
            echo $user->posts->count();
        }
    }

    public function conditionalReassignment(bool $replace): void
    {
        $users = User::all();
        if ($replace) {
            $users = null;
        }
        foreach ($users as $user) {
            echo $user->posts->count();
        }
    }

    public function fixedModelLoop(bool $run): void
    {
        $user = User::first();
        while ($run) {
            echo $user->posts->count();
            $run = false;
        }
    }
}
