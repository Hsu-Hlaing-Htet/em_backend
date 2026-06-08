<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AuthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->when(
                $this->relationLoaded('role'),
                fn () => $this->getRelation('role')->name
            ),
            'profile' => $this->when(
                $this->relationLoaded('profile') && $this->getRelation('profile') !== null,
                fn () => [
                    'phone' => $this->getRelation('profile')->phone,
                    'nrc' => $this->getRelation('profile')->nrc,
                    'dob' => $this->getRelation('profile')->dob?->toDateString(),
                    'gender' => $this->getRelation('profile')->gender,
                    'address' => $this->getRelation('profile')->address,
                    'avatar_path' => $this->getRelation('profile')->avatar_path,
                ]
            ),
        ];
    }
}
