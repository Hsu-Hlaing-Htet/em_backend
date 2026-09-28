<?php

namespace App\Services;

use App\Models\ContactInquiry;
use App\Services\Concerns\AppliesListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContactInquiryService
{
    use AppliesListQuery;

    /**
     * @param  array<string, mixed>  $params
     */
    public function paginate(array $params): LengthAwarePaginator
    {
        $query = ContactInquiry::query();

        $this->applyStatusFilter($query, $params);
        $this->applyListQuery(
            $query,
            $params,
            ['name', 'email', 'phone', 'subject', 'preferred_service', 'message', 'status'],
            [
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'preferred_service' => 'preferred_service',
                'subject' => 'subject',
                'status' => 'status',
                'created_at' => 'created_at',
            ],
        );

        return $query->paginate((int) ($params['per_page'] ?? 10));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ContactInquiry
    {
        return ContactInquiry::query()->create([
            ...$data,
            'status' => ContactInquiry::STATUS_NEW,
            'read_at' => null,
        ]);
    }

    public function markAsRead(ContactInquiry $inquiry): ContactInquiry
    {
        return $inquiry->markAsRead();
    }
}
