<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class ResidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->whenLoaded('profile', fn () => $this->profile?->phone),
            'nrc' => $this->whenLoaded('profile', fn () => $this->profile?->nrc),
            'dob' => $this->whenLoaded('profile', fn () => $this->profile?->dob?->toDateString()),
            'gender' => $this->whenLoaded('profile', fn () => $this->profile?->gender),
            'address' => $this->whenLoaded('profile', fn () => $this->profile?->address),
            'avatar_path' => $this->whenLoaded('profile', fn () => $this->profile?->avatar_path),
        ];
    }
}
