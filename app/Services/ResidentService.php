<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResidentService
{
    public function find(int $id): User
    {
        return User::query()
            ->with(['role', 'profile'])
            ->whereHas('role', fn ($query) => $query->where('name', Role::CUSTOMER))
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
            ]);

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => $data['phone'],
                    'nrc' => $data['nrc'],
                    'dob' => $data['dob'],
                    'gender' => $data['gender'],
                    'address' => $data['address'],
                    'avatar_path' => $data['avatar_path'] ?? null,
                ],
            );

            return $user->fresh(['role', 'profile']);
        });
    }
}
