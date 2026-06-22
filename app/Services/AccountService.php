<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AccountService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     * @param  list<string>  $roleNames
     */
    public function paginate(array $params, array $roleNames): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['role', 'profile'])
            ->whereHas('role', fn (Builder $q) => $q->whereIn('name', $roleNames));

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

        $this->applyAccountOrdering($query, $params);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id, array $roleNames): User
    {
        $user = User::query()
            ->with(['role', 'profile'])
            ->findOrFail($id);

        if (! in_array($user->role?->name, $roleNames, true)) {
            abort(404);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $roleId, UserService $userService, ProfileService $profileService): User
    {
        return DB::transaction(function () use ($data, $roleId, $userService, $profileService): User {
            $user = $userService->create([
                ...$this->splitUserData($data),
                'role_id' => $roleId,
            ]);

            $profileService->create([
                ...$this->splitProfileData($data),
                'user_id' => $user->id,
            ]);

            return $user->fresh(['role', 'profile']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, UserService $userService, ProfileService $profileService): User
    {
        return DB::transaction(function () use ($user, $data, $userService, $profileService): User {
            $userData = $this->splitUserData($data);

            if (isset($data['role_id'])) {
                $userData['role_id'] = $data['role_id'];
            }

            $user = $userService->update($user, $userData);

            $profileData = $this->splitProfileData($data);

            if ($user->profile) {
                $profileService->update($user->profile, $profileData);
            } else {
                $profileService->create([
                    ...$profileData,
                    'user_id' => $user->id,
                ]);
            }

            return $user->fresh(['role', 'profile']);
        });
    }

    public function delete(User $user, UserService $userService): void
    {
        $userService->delete($user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function splitUserData(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'email', 'password', 'role_id']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function splitProfileData(array $data): array
    {
        return array_intersect_key($data, array_flip(['phone', 'nrc', 'dob', 'gender', 'address', 'avatar_path']));
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $params
     */
    private function applyAccountOrdering(Builder $query, array $params): void
    {
        if (empty($params['order'])) {
            $query->latest('users.id');

            return;
        }

        $profileFields = ['phone', 'nrc', 'gender'];
        $joinedProfiles = false;
        $joinedRoles = false;

        foreach (explode(',', (string) $params['order']) as $sort) {
            [$field, $direction] = array_pad(explode('|', $sort), 2, 'asc');
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, $profileFields, true)) {
                if (! $joinedProfiles) {
                    $query->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
                        ->select('users.*');
                    $joinedProfiles = true;
                }

                $query->orderBy('profiles.'.$field, $direction);

                continue;
            }

            if ($field === 'role_name') {
                if (! $joinedRoles) {
                    $query->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                        ->select('users.*');
                    $joinedRoles = true;
                }

                $query->orderBy('roles.name', $direction);

                continue;
            }

            $query->orderBy('users.'.$field, $direction);
        }
    }
}
