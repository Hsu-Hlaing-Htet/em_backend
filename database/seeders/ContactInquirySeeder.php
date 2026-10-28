<?php

namespace Database\Seeders;

use App\Models\ContactInquiry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

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

        $visitors = [
            ['Aung Min Khant', 'aungminkhant@gmail.com'],
            ['Su Myat Noe', 'sumyatnoe@gmail.com'],
            ['Htet Naing Win', 'htetnaingwin@gmail.com'],
            ['Thiri Mon', 'thirimon@gmail.com'],
            ['Kyaw Zin Htet', 'kyawzinhtet@gmail.com'],
            ['Ei Phyu Sin', 'eiphyusin@gmail.com'],
            ['Min Thu Aung', 'minthuaung@gmail.com'],
            ['Khin Wint Wah', 'khinwintwah@gmail.com'],
            ['Zaw Ye Htun', 'zawyehtun@gmail.com'],
            ['Nandar Hlaing', 'nandarhlaing@gmail.com'],
            ['Aung Pyae Sone', 'aungpyaesone@gmail.com'],
            ['May Thazin Oo', 'maythazinoo@gmail.com'],
            ['Hein Htet Aung', 'heinhtetaung@gmail.com'],
            ['Hnin Pwint Phyu', 'hninpwintphyu@gmail.com'],
            ['Ye Yint Aung', 'yeyintaung@gmail.com'],
        ];

        foreach ($visitors as $index => [$name, $email]) {
            $i = $index + 1;
            $topic = $topics[$index % count($topics)];

            $subject = sprintf(
                'Rosewood inquiry %03d: %s',
                $i,
                $topic
            );

            $createdAt = Carbon::parse('2026-06-01')
                ->addDays($index);

            $status = $i % 3 === 0
                ? ContactInquiry::STATUS_READ
                : ContactInquiry::STATUS_NEW;

            $inquiry = ContactInquiry::query()->firstOrCreate(
                [
                    'subject' => $subject,
                    'email' => $email,
                ],
                [
                    'name' => $name,
                    'phone' => sprintf('+9599800%04d', $i),
                    'preferred_service' =>
                        ContactInquiry::PREFERRED_SERVICES[
                            $index % count(
                                ContactInquiry::PREFERRED_SERVICES
                            )
                        ],
                    'message' => sprintf(
                        'I would like more information about %s at Rosewood Royale.',
                        strtolower($topic)
                    ),
                    'status' => $status,
                    'read_at' => $status === ContactInquiry::STATUS_READ
                        ? $createdAt->copy()->addDay()
                        : null,
                ]
            );

            if ($inquiry->wasRecentlyCreated) {
                $inquiry->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' =>
                        $status === ContactInquiry::STATUS_READ
                            ? $createdAt->copy()->addDay()
                            : $createdAt,
                ])->save();
            }
        }
    }
}
