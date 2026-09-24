<?php

namespace Database\Seeders\Support;

final class MyanmarSampleData
{
    private const BUILDING_NAMES = [
        'Rosewood Tower',
        'Royal Residence',
        'Garden View Residence',
        'Emerald Heights',
        'Mandalay Residence',
        'Inya Lake Residence',
        'Golden Hill Residence',
        'Shwe Garden Residence',
        'Pearl River Tower',
        'Jasmine Court',
        'Lotus Garden Residence',
        'Sakura Heights',
        'Heritage Residence',
        'Vista Point Residence',
        'Orchid Residence',
        'Ruby Garden Tower',
        'Diamond Crest Residence',
        'Sunrise Residence',
        'Silver Leaf Tower',
        'Pinnacle Residence',
        'Grand Royal Residence',
        'Lakeview Heights',
        'Green Valley Residence',
        'City Park Tower',
        'Royal Avenue Residence',
        'The Magnolia Residence',
        'The Marigold Tower',
        'Golden Valley Residence',
        'Mingalar Residence',
        'Yadanar Heights',
        'Aung Mingalar Tower',
        'Thanlwin Residence',
        'Irrawaddy Heights',
        'Kandawgyi Residence',
        'Parami Garden Residence',
        'Mayangone Heights',
        'Sanchaung Residence',
        'Bahan Garden Tower',
        'Kamayut Residence',
        'Dagon Heights',
    ];

    private const BUILDING_LOCATIONS = [
        'Kamayut Township, Yangon, Myanmar',
        'Bahan Township, Yangon, Myanmar',
        'Mayangone Township, Yangon, Myanmar',
        'Yankin Township, Yangon, Myanmar',
        'Hlaing Township, Yangon, Myanmar',
        'Dagon Township, Yangon, Myanmar',
        'Tamwe Township, Yangon, Myanmar',
        'Sanchaung Township, Yangon, Myanmar',
        'Chanmyathazi Township, Mandalay, Myanmar',
        'Pyigyidagun Township, Mandalay, Myanmar',
    ];

    /**
     * @return list<array{name: string, email: string, phone: string, nrc: string, dob: string, gender: string, address: string}>
     */
    public static function customers(): array
    {
        $customers = [
            ['name' => 'Mg Mg', 'phone' => '+95942011100', 'nrc' => '12/YaKaNa(N)111001', 'dob' => '1992-04-12', 'gender' => 'male', 'address' => 'No. 12, Pyay Road, Kamayut Township, Yangon'],
            ['name' => 'Ma Hla Hla', 'phone' => '+95942111100', 'nrc' => '12/BaKaTa(N)111002', 'dob' => '1994-08-03', 'gender' => 'female', 'address' => 'No. 45, Inya Road, Bahan Township, Yangon'],
            ['name' => 'Ko Ko', 'phone' => '+95942211100', 'nrc' => '12/LaKaNa(N)111003', 'dob' => '1990-11-21', 'gender' => 'male', 'address' => 'No. 78, Kabar Aye Pagoda Road, Mayangone Township, Yangon'],
            ['name' => 'Ma Su Su', 'phone' => '+95942311100', 'nrc' => '12/MaNyaTa(N)111004', 'dob' => '1996-01-15', 'gender' => 'female', 'address' => 'No. 19, University Avenue, Bahan Township, Yangon'],
            ['name' => 'U Myint Myint', 'phone' => '+95942411100', 'nrc' => '12/PaBaTa(N)111005', 'dob' => '1988-06-30', 'gender' => 'male', 'address' => 'No. 6, Shwe Gon Daing Road, Bahan Township, Yangon'],
            ['name' => 'Daw Khin Khin', 'phone' => '+95942511100', 'nrc' => '12/TaKaNa(N)111006', 'dob' => '1987-02-18', 'gender' => 'female', 'address' => 'No. 33, Kaba Aye Pagoda Road, Yankin Township, Yangon'],
            ['name' => 'Ko Zaw Zaw', 'phone' => '+95942611100', 'nrc' => '12/YaKaNa(N)111007', 'dob' => '1993-09-09', 'gender' => 'male', 'address' => 'No. 102, Pyi Road, Dagon Township, Yangon'],
            ['name' => 'Ma Phyu Phyu', 'phone' => '+95942711100', 'nrc' => '12/BaKaTa(N)111008', 'dob' => '1995-12-02', 'gender' => 'female', 'address' => 'No. 27, Parami Road, Hlaing Township, Yangon'],
            ['name' => 'U Htun Htun', 'phone' => '+95942811100', 'nrc' => '12/LaKaNa(N)111009', 'dob' => '1986-05-25', 'gender' => 'male', 'address' => 'No. 58, Strand Road, Pabedan Township, Yangon'],
            ['name' => 'Ma Nwe Nwe', 'phone' => '+95942911101', 'nrc' => '12/MaNyaTa(N)111010', 'dob' => '1997-07-07', 'gender' => 'female', 'address' => 'No. 14, Anawrahta Road, Latha Township, Yangon'],
            ['name' => 'Ko Aung Kyaw', 'phone' => '+95943011101', 'nrc' => '12/PaBaTa(N)111011', 'dob' => '1991-03-14', 'gender' => 'male', 'address' => 'No. 91, Bogyoke Aung San Road, Pabedan Township, Yangon'],
            ['name' => 'Ma Thin Thin', 'phone' => '+95943111101', 'nrc' => '12/TaKaNa(N)111012', 'dob' => '1998-10-28', 'gender' => 'female', 'address' => 'No. 23, Mahabandoola Road, Kyauktada Township, Yangon'],
            ['name' => 'U Tun Tun', 'phone' => '+95943211101', 'nrc' => '12/YaKaNa(N)111013', 'dob' => '1989-08-19', 'gender' => 'male', 'address' => 'No. 67, Pyay Road, Mayangone Township, Yangon'],
            ['name' => 'Ma Ei Ei', 'phone' => '+95943311101', 'nrc' => '12/BaKaTa(N)111014', 'dob' => '1994-04-04', 'gender' => 'female', 'address' => 'No. 41, Insein Road, Hlaing Township, Yangon'],
            ['name' => 'Ko Min Min', 'phone' => '+95943411101', 'nrc' => '12/LaKaNa(N)111015', 'dob' => '1992-12-11', 'gender' => 'male', 'address' => 'No. 88, Waizayantar Road, South Okkalapa Township, Yangon'],
            ['name' => 'Ma Yee Yee', 'phone' => '+95943511101', 'nrc' => '12/MaNyaTa(N)111016', 'dob' => '1996-06-16', 'gender' => 'female', 'address' => 'No. 52, Thumingalar Road, Thingangyun Township, Yangon'],
            ['name' => 'U Sein Sein', 'phone' => '+95943611101', 'nrc' => '12/PaBaTa(N)111017', 'dob' => '1985-01-23', 'gender' => 'male', 'address' => 'No. 74, Pyi Htaung Su Road, Dagon Township, Yangon'],
            ['name' => 'Ma Hnin Hnin', 'phone' => '+95943711101', 'nrc' => '12/TaKaNa(N)111018', 'dob' => '1999-02-02', 'gender' => 'female', 'address' => 'No. 16, Kyaik Wine Pagoda Road, Mayangone Township, Yangon'],
            ['name' => 'Ko Wai Wai', 'phone' => '+95943811101', 'nrc' => '12/YaKaNa(N)111019', 'dob' => '1993-05-05', 'gender' => 'male', 'address' => 'No. 29, Natmauk Road, Tamwe Township, Yangon'],
            ['name' => 'Ma Thandar', 'phone' => '+95943911102', 'nrc' => '12/BaKaTa(N)111020', 'dob' => '1995-09-27', 'gender' => 'female', 'address' => 'No. 63, U Wisara Road, Sanchaung Township, Yangon'],
        ];

        $usedEmails = [];

        return array_map(function (array $customer) use (&$usedEmails): array {
            return [
                ...$customer,
                'email' => self::uniqueEmailForName($customer['name'], $usedEmails),
            ];
        }, $customers);
    }

    /**
     * @return list<array{building_name: string, location: string, description: string}>
     */
    public static function buildings(): array
    {
        $buildings = [];

        for ($i = 0; $i < 40; $i++) {
            $buildings[] = [
                'building_name' => self::buildingNameForIndex($i),
                'location' => self::BUILDING_LOCATIONS[$i % count(self::BUILDING_LOCATIONS)],
                'description' => 'Modern residential building with 24-hour security, backup power, and covered parking.',
            ];
        }

        return $buildings;
    }

    public static function randomNrc(): string
    {
        $codes = ['YaKaNa', 'BaKaTa', 'LaKaNa', 'MaNyaTa', 'PaBaTa', 'TaKaNa'];

        return sprintf(
            '12/%s(N)%06d',
            $codes[array_rand($codes)],
            random_int(100000, 999999),
        );
    }

    public static function randomPhone(): string
    {
        return self::phoneForIndex(random_int(1, 999999));
    }

    public static function phoneForIndex(int $index): string
    {
        return sprintf('+959%08d', 40000000 + ($index % 50000000));
    }

    public static function buildingNameForIndex(int $index): string
    {
        $index = max(0, $index);

        if (isset(self::BUILDING_NAMES[$index])) {
            return self::BUILDING_NAMES[$index];
        }

        return 'Rosewood Residence '.($index + 1);
    }

    /**
     * @param  array<string, true>  $usedEmails
     */
    public static function uniqueEmailForName(string $name, ?array &$usedEmails = null): string
    {
        static $defaultUsedEmails = [];

        if ($usedEmails === null) {
            $usedEmails = &$defaultUsedEmails;
        }

        $base = self::emailBaseForName($name);
        $email = $base.'@gmail.com';
        $suffix = 2;

        while (isset($usedEmails[$email]) || isset($usedEmails[strtolower($email)])) {
            $email = $base.$suffix.'@gmail.com';
            $suffix++;
        }

        $usedEmails[$email] = true;
        $usedEmails[strtolower($email)] = true;

        return $email;
    }

    public static function emailBaseForName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) > 1 && in_array(strtolower($parts[0]), ['u', 'daw', 'ko', 'ma'], true)) {
            array_shift($parts);
        }

        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', implode('', $parts)) ?? '');

        return $base !== '' ? $base : 'user';
    }

    public static function randomYangonAddress(): string
    {
        $roads = [
            'Pyay Road',
            'Inya Road',
            'Kabar Aye Pagoda Road',
            'University Avenue',
            'Shwe Gon Daing Road',
            'Parami Road',
            'Strand Road',
        ];
        $townships = [
            'Kamayut Township, Yangon',
            'Bahan Township, Yangon',
            'Mayangone Township, Yangon',
            'Hlaing Township, Yangon',
        ];

        return sprintf(
            'No. %d, %s, %s',
            random_int(1, 200),
            $roads[array_rand($roads)],
            $townships[array_rand($townships)],
        );
    }

    /**
     * Deterministic bulk customers for volume seeding (idempotent emails).
     *
     * @return list<array{name: string, email: string, phone: string, nrc: string, dob: string, gender: string, address: string}>
     */
    public static function bulkCustomers(int $count = 80, int $startIndex = 21): array
    {
        $maleTitles = ['U', 'Ko', 'Mg'];
        $femaleTitles = ['Daw', 'Ma'];
        $givenNames = [
            'Aung', 'Thura', 'Zaw', 'Myo', 'Lin', 'Naing', 'Htet', 'Win', 'Oo', 'Kyaw',
            'Soe', 'Tun', 'Min', 'Phyo', 'Ye', 'Kaung', 'Hla', 'Nyein', 'Pyae', 'Thant',
            'Su', 'Moe', 'Hnin', 'Yee', 'Thiri', 'Nandar', 'Chit', 'May', 'Khin', 'Aye',
            'Ei', 'Phyu', 'Thin', 'Sandar', 'Wai', 'Thida', 'Myat', 'Cho', 'Nwe', 'Lai',
        ];
        $roads = [
            'Pyay Road', 'Inya Road', 'Kabar Aye Pagoda Road', 'University Avenue',
            'Shwe Gon Daing Road', 'Parami Road', 'Strand Road', 'Anawrahta Road',
            'Bogyoke Aung San Road', 'Mahabandoola Road', 'U Wisara Road', 'Natmauk Road',
        ];
        $townships = [
            'Kamayut Township, Yangon', 'Bahan Township, Yangon', 'Mayangone Township, Yangon',
            'Hlaing Township, Yangon', 'Yankin Township, Yangon', 'Tamwe Township, Yangon',
            'Sanchaung Township, Yangon', 'Dagon Township, Yangon', 'Thingangyun Township, Yangon',
            'South Okkalapa Township, Yangon',
        ];
        $nrcCodes = ['YaKaNa', 'BaKaTa', 'LaKaNa', 'MaNyaTa', 'PaBaTa', 'TaKaNa'];

        $customers = [];
        $usedEmails = [];

        foreach (self::customers() as $customer) {
            $usedEmails[$customer['email']] = true;
        }

        // Avoid colliding with already-seeded users when expanding the bulk set.
        if (class_exists(\App\Models\User::class)) {
            try {
                foreach (\App\Models\User::query()->pluck('email') as $email) {
                    if (is_string($email) && $email !== '') {
                        $usedEmails[strtolower($email)] = true;
                    }
                }
            } catch (\Throwable) {
                // Seeder may run before DB is available in some tooling contexts.
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $index = $startIndex + $i;
            $isFemale = $index % 2 === 0;
            $title = $isFemale
                ? $femaleTitles[$index % count($femaleTitles)]
                : $maleTitles[$index % count($maleTitles)];
            $first = $givenNames[$index % count($givenNames)];
            $second = $givenNames[($index * 3) % count($givenNames)];
            $name = $title.' '.$first.' '.$second;
            $email = self::uniqueEmailForName($name, $usedEmails);
            $phone = self::phoneForIndex($index);
            $nrc = sprintf('12/%s(N)%06d', $nrcCodes[$index % count($nrcCodes)], 200000 + $index);
            $year = 1984 + ($index % 18);
            $month = 1 + ($index % 12);
            $day = 1 + ($index % 27);

            $customers[] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'nrc' => $nrc,
                'dob' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'gender' => $isFemale ? 'female' : 'male',
                'address' => sprintf(
                    'No. %d, %s, %s',
                    10 + ($index % 180),
                    $roads[$index % count($roads)],
                    $townships[$index % count($townships)],
                ),
            ];
        }

        return $customers;
    }

    /**
     * Extra Myanmar buildings for bulk seeding (deterministic names).
     *
     * @return list<array{building_name: string, location: string, description: string}>
     */
    public static function bulkBuildings(): array
    {
        return array_slice(self::buildings(), 2, 20);
    }
}
