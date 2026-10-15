<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends BaseModel
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
