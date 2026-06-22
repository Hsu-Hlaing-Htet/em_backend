<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->whenLoaded('profile', fn () => $this->profile?->id),
            'role_id' => $this->role_id,
            'role_name' => $this->whenLoaded('role', fn () => $this->role?->name),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->whenLoaded('profile', fn () => $this->profile?->phone),
            'nrc' => $this->whenLoaded('profile', fn () => $this->profile?->nrc),
            'dob' => $this->whenLoaded('profile', fn () => $this->profile?->dob?->toDateString()),
            'gender' => $this->whenLoaded('profile', fn () => $this->profile?->gender),
            'address' => $this->whenLoaded('profile', fn () => $this->profile?->address),
            'avatar_path' => $this->whenLoaded('profile', fn () => $this->profile?->avatar_path),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
