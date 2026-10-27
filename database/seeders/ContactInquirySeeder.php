<?php

namespace Database\Seeders;

use App\Models\ContactInquiry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Deterministic public-contact workflow examples for the standard seed. */
class ContactInquirySeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            'Property availability',
            'Sale pricing',
            'Rental viewing',
            'Contract information',
            'Estate services',
        ];

        for ($i = 1; $i <= 100; $i++) {
            $subject = sprintf('Rosewood inquiry %03d: %s', $i, $topics[$i % count($topics)]);
            $email = sprintf('visitor%03d@example.test', $i);
            $createdAt = Carbon::parse('2026-06-01')->addDays($i - 1);
            $status = $i % 3 === 0 ? ContactInquiry::STATUS_READ : ContactInquiry::STATUS_NEW;

            $inquiry = ContactInquiry::query()->firstOrCreate(
                ['subject' => $subject, 'email' => $email],
                [
                    'name' => sprintf('Demo Visitor %03d', $i),
                    'phone' => sprintf('+9599800%04d', $i),
                    'preferred_service' => ContactInquiry::PREFERRED_SERVICES[$i % count(ContactInquiry::PREFERRED_SERVICES)],
                    'message' => sprintf('Please send information about %s at Rosewood Royale.', strtolower($topics[$i % count($topics)])),
                    'status' => $status,
                    'read_at' => $status === ContactInquiry::STATUS_READ ? $createdAt->copy()->addDay() : null,
                ],
            );

            if ($inquiry->wasRecentlyCreated) {
                $inquiry->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' => $status === ContactInquiry::STATUS_READ ? $createdAt->copy()->addDay() : $createdAt,
                ])->save();
            }
        }
    }
}
