<?php

namespace App\Services;

use App\Models\Profile;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProfileService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = Profile::query()->with('user');
        $this->applyListQuery($query, $params, ['phone', 'nrc', 'gender', 'address']);

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    public function find(int $id): Profile
    {
        return Profile::query()->with('user')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Profile
    {
        return Profile::query()->create($data)->load('user');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Profile $profile, array $data): Profile
    {
        $profile->update($data);

        return $profile->fresh('user');
    }

    public function delete(Profile $profile): void
    {
        $profile->delete();
    }
}
