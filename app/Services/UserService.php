<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = User::query()->with('role');
        $this->applyListQuery($query, $params, ['name', 'email']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): User
    {
        return User::query()->with(['role', 'profile'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return User::query()->create($data)->load('role');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user->fresh(['role', 'profile']);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
