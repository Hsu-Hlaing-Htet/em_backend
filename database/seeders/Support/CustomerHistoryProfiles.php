<?php

namespace Database\Seeders\Support;

final class CustomerHistoryProfiles
{
    /**
     * @return array<string, list<string>>
     */
    public static function emailsByPersona(): array
    {
        return [
            'active_rent' => [
                'mgmg@gmail.com',
                'hlahla@gmail.com',
                'koko@gmail.com',
            ],
            'active_sale' => [
                'susu@gmail.com',
                'myintmyint@gmail.com',
                'khinkhin@gmail.com',
            ],
            'former_rent' => [
                'zawzaw@gmail.com',
                'phyuphyu@gmail.com',
                'htunhtun@gmail.com',
            ],
            'former_sale' => [
                'nwenwe@gmail.com',
                'aungkyaw@gmail.com',
                'thinthin@gmail.com',
            ],
            'registered_only' => [
                'tuntun@gmail.com',
                'eiei@gmail.com',
                'minmin@gmail.com',
            ],
            'pipeline' => [
                'yeyee@gmail.com',
                'seinsein@gmail.com',
                'hninhnin@gmail.com',
                'waiwai@gmail.com',
                'thandar@gmail.com',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allAssignedEmails(): array
    {
        return collect(self::emailsByPersona())
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }
}
