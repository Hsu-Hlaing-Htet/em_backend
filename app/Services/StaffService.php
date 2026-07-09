<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffService
{
    use AppliesListQuery;

    /**
     * @var list<string>
     */
    private array $staffRoles = [Role::SUPER_ADMIN, Role::ADMIN];

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['role', 'profile'])
            ->whereHas('role', fn (Builder $builder) => $builder->whereIn('name', $this->staffRoles));

        if (! empty($params['search'])) {
            $search = $params['search'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('profile', function (Builder $profileQuery) use ($search): void {
                        $profileQuery->where('phone', 'like', '%'.$search.'%')
                            ->orWhere('nrc', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($params['order'])) {
            foreach (explode(',', (string) $params['order']) as $sort) {
                [$field, $direction] = array_pad(explode('|', $sort), 2, 'asc');
                $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                $query->orderBy($field, $direction);
            }
        } else {
            $query->latest('id');
        }

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): User
    {
        return User::query()
            ->with(['role', 'profile'])
            ->whereHas('role', fn (Builder $builder) => $builder->whereIn('name', $this->staffRoles))
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'role_id' => $data['role_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            Profile::query()->create([
                'user_id' => $user->id,
                'phone' => $data['phone'],
                'nrc' => $data['nrc'],
                'dob' => $data['dob'],
                'gender' => $data['gender'],
                'address' => $data['address'],
                'avatar_path' => $data['avatar_path'] ?? null,
            ]);

            return $user->load(['role', 'profile']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $userData = [
                'role_id' => $data['role_id'],
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (! empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }

            $user->update($userData);

            $profileData = [
                'phone' => $data['phone'],
                'nrc' => $data['nrc'],
                'dob' => $data['dob'],
                'gender' => $data['gender'],
                'address' => $data['address'],
                'avatar_path' => $data['avatar_path'] ?? null,
            ];

            if ($user->profile) {
                $user->profile->update($profileData);
            } else {
                Profile::query()->create([
                    'user_id' => $user->id,
                    ...$profileData,
                ]);
            }

            return $user->fresh(['role', 'profile']);
        });
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
