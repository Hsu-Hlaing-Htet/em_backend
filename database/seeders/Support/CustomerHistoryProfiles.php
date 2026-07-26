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
                'mgmg@rosewoodroyale.com',
                'hlahla@rosewoodroyale.com',
                'koko@rosewoodroyale.com',
            ],
            'active_sale' => [
                'susu@rosewoodroyale.com',
                'myintmyint@rosewoodroyale.com',
                'khinkhin@rosewoodroyale.com',
            ],
            'former_rent' => [
                'zawzaw@rosewoodroyale.com',
                'phyuphyu@rosewoodroyale.com',
                'htunhtun@rosewoodroyale.com',
            ],
            'former_sale' => [
                'nwenwe@rosewoodroyale.com',
                'aungkyaw@rosewoodroyale.com',
                'thinthin@rosewoodroyale.com',
            ],
            'registered_only' => [
                'tuntun@rosewoodroyale.com',
                'eiei@rosewoodroyale.com',
                'minmin@rosewoodroyale.com',
            ],
            'pipeline' => [
                'yeyee@rosewoodroyale.com',
                'seinsein@rosewoodroyale.com',
                'hninhnin@rosewoodroyale.com',
                'waiwai@rosewoodroyale.com',
                'thandar@rosewoodroyale.com',
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
