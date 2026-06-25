<?php

namespace App\Services;

use App\Models\Role;
use App\Services\Concerns\AppliesListQuery;
use App\Services\Concerns\GuardsDeletion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class RoleService
{
    use AppliesListQuery, GuardsDeletion;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Role::query();
        $this->applyListQuery($query, $params, ['name']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Role
    {
        return Role::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Role
    {
        return Role::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role->fresh();
    }

    public function delete(Role $role): void
    {
        $protectedRoles = [Role::SUPER_ADMIN, Role::ADMIN, Role::CUSTOMER];

        if (in_array($role->name, $protectedRoles, true)) {
            throw ValidationException::withMessages([
                'delete' => 'Cannot delete a system role.',
            ]);
        }

        $this->guardNoChildren(
            $role,
            'users',
            'Cannot delete this role because it is assigned to users. Reassign users to another role first.',
        );

        $role->delete();
    }
}
