<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'posts' => PostResource::collection($this->posts),
            'safe' => PostResource::collection($this->whenLoaded('posts')),
        ];
    }
}
