<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Support\MyanmarSampleData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * @var list<array{role: string, name: string, email: string, password: string}>
     */
    private static array $credentials = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        self::$credentials = [];

        $superAdminRole = Role::findByName(Role::SUPER_ADMIN);
        $adminRole = Role::findByName(Role::ADMIN);
        $customerRole = Role::findByName(Role::CUSTOMER);
        $staffRoleIds = Role::query()
            ->whereIn('name', Role::staffRoleNames())
            ->pluck('id', 'name');

        if (! $superAdminRole || ! $adminRole || ! $customerRole || $staffRoleIds->count() !== count(Role::staffRoleNames())) {
            $this->command?->error('Roles must be seeded before users. Run RoleSeeder first.');

            return;
        }

        $password = 'p@ssword';
        $usedEmails = ['admin@rosewoodroyale.com' => true];

        $this->createUserWithProfile(
            $superAdminRole->id,
            'U Kyaw Swar',
            'admin@rosewoodroyale.com',
            $password,
            [
                'phone' => '+95942012345',
                'nrc' => '12/YaKaNa(N)123456',
                'dob' => '1985-03-15',
                'gender' => 'male',
                'address' => 'No. 1, Pyay Road, Kamayut Township, Yangon',
            ]
        );

        // Keep the established super-admin fixture used by application tests,
        // and add the required named UOG demonstration login without changing
        // any existing account or resetting its password on repeat seeding.
        $this->createUserWithProfile(
            $superAdminRole->id,
            'U Kyaw Swar',
            'superadmin@rosewood.com',
            $password,
            [
                'phone' => '+95942012346',
                'nrc' => 'DEMO-UOG-SUPERADMIN',
                'dob' => '1985-03-15',
                'gender' => 'male',
                'address' => 'Demonstration address, Yangon',
            ]
        );

        $this->createUserWithProfile(
            $adminRole->id,
            'Aung Aung',
            MyanmarSampleData::uniqueEmailForName('Aung Aung', $usedEmails),
            $password,
            [
                'phone' => '+95942123456',
                'nrc' => '12/BaKaTa(N)234567',
                'dob' => '1990-07-22',
                'gender' => 'male',
                'address' => 'No. 25, Inya Road, Bahan Township, Yangon',
            ]
        );

        foreach (self::staffMembers() as $staffMember) {
            $this->createInactiveStaffWithProfile((int) $staffRoleIds[$staffMember['role']], $staffMember);
        }

        foreach (MyanmarSampleData::customers() as $customer) {
            $this->createUserWithProfile(
                $customerRole->id,
                $customer['name'],
                $customer['email'],
                $password,
                [
                    'phone' => $customer['phone'],
                    'nrc' => $customer['nrc'],
                    'dob' => $customer['dob'],
                    'gender' => $customer['gender'],
                    'address' => $customer['address'],
                ]
            );
        }
    }

    /**
     * @return list<array{role: string, name: string, email: string, password: string}>
     */
    public static function credentials(): array
    {
        return self::$credentials;
    }

    /**
     * @param  array{phone: string, nrc: string, dob: string, gender: string, address: string}  $profileData
     */
    private function createUserWithProfile(
        int $roleId,
        string $name,
        string $email,
        string $password,
        array $profileData
    ): void {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'role_id' => $roleId,
                'name' => $name,
                'password' => $password,
            ]
        );

        Profile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                ...$profileData,
                'avatar_path' => null,
            ]
        );

        $this->recordCredentials($user, $password);
    }

    private function recordCredentials(User $user, string $password = 'p@ssword'): void
    {
        $user->loadMissing('role');

        self::$credentials[] = [
            'role' => $user->role?->name ?? 'unknown',
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
        ];
    }

    /**
     * @return list<array{role: string, name: string, email: string, phone: string, nrc: string, dob: string, gender: string, address: string}>
     */
    private static function staffMembers(): array
    {
        return [
            ['name' => 'May Thazin Win', 'email' => 'maythazin@gmail.com', 'role' => Role::MANAGER, 'phone' => '+95950100001', 'nrc' => '12/KaMaTa(N)100001', 'dob' => '1987-02-14', 'gender' => 'female', 'address' => 'Kamayut Township, Yangon'],
            ['name' => 'Kyaw Zin Htet', 'email' => 'kyawzinhtet@gmail.com', 'role' => Role::MANAGER, 'phone' => '+95950100002', 'nrc' => '12/OuKaTa(N)100002', 'dob' => '1985-09-03', 'gender' => 'male', 'address' => 'Hlaing Township, Yangon'],
            ['name' => 'Ei Ei Mon', 'email' => 'eieimon@gmail.com', 'role' => Role::ACCOUNTANT, 'phone' => '+95950100003', 'nrc' => '12/LaMaNa(N)100003', 'dob' => '1990-06-21', 'gender' => 'female', 'address' => 'Bahan Township, Yangon'],
            ['name' => 'Htet Aung', 'email' => 'htetaung@gmail.com', 'role' => Role::ACCOUNTANT, 'phone' => '+95950100004', 'nrc' => '12/DaGaNa(N)100004', 'dob' => '1988-11-18', 'gender' => 'male', 'address' => 'Sanchaung Township, Yangon'],
            ['name' => 'Su Myat Noe', 'email' => 'sumyatnoe@gmail.com', 'role' => Role::ACCOUNTANT, 'phone' => '+95950100005', 'nrc' => '12/PaBaTa(N)100005', 'dob' => '1992-01-29', 'gender' => 'female', 'address' => 'Mayangone Township, Yangon'],
            ['name' => 'Thandar Hlaing', 'email' => 'thandarhlaing@gmail.com', 'role' => Role::RECEPTIONIST, 'phone' => '+95950100006', 'nrc' => '12/YaKaNa(N)100006', 'dob' => '1994-04-12', 'gender' => 'female', 'address' => 'Tamwe Township, Yangon'],
            ['name' => 'Nay Lin Oo', 'email' => 'naylinoo@gmail.com', 'role' => Role::RECEPTIONIST, 'phone' => '+95950100007', 'nrc' => '12/TaKaTa(N)100007', 'dob' => '1991-08-25', 'gender' => 'male', 'address' => 'Thingangyun Township, Yangon'],
            ['name' => 'Phyu Phyu Wai', 'email' => 'phyu.phyu.wai@gmail.com', 'role' => Role::RECEPTIONIST, 'phone' => '+95950100008', 'nrc' => '12/MaBaNa(N)100008', 'dob' => '1995-12-07', 'gender' => 'female', 'address' => 'Yankin Township, Yangon'],
            ['name' => 'Aung Myint', 'email' => 'aungmyint@gmail.com', 'role' => Role::SECURITY_GUARD, 'phone' => '+95950100009', 'nrc' => '12/DaPaNa(N)100009', 'dob' => '1986-03-05', 'gender' => 'male', 'address' => 'North Okkalapa Township, Yangon'],
            ['name' => 'Myo Min Tun', 'email' => 'myomintun@gmail.com', 'role' => Role::SECURITY_GUARD, 'phone' => '+95950100010', 'nrc' => '12/LaThaNa(N)100010', 'dob' => '1989-07-16', 'gender' => 'male', 'address' => 'South Okkalapa Township, Yangon'],
            ['name' => 'Zaw Win Naing', 'email' => 'zawwinnaing@gmail.com', 'role' => Role::SECURITY_GUARD, 'phone' => '+95950100011', 'nrc' => '12/AhLaNa(N)100011', 'dob' => '1984-10-30', 'gender' => 'male', 'address' => 'Mingaladon Township, Yangon'],
            ['name' => 'Ko Ko Gyi', 'email' => 'kokogyi@gmail.com', 'role' => Role::SECURITY_GUARD, 'phone' => '+95950100012', 'nrc' => '12/KaTaTa(N)100012', 'dob' => '1983-05-09', 'gender' => 'male', 'address' => 'Insein Township, Yangon'],
            ['name' => 'Ye Min Htun', 'email' => 'yeminhtun@gmail.com', 'role' => Role::SECURITY_GUARD, 'phone' => '+95950100013', 'nrc' => '12/PaZaTa(N)100013', 'dob' => '1990-02-27', 'gender' => 'male', 'address' => 'Dagon Seikkan Township, Yangon'],
            ['name' => 'Khin Mar Win', 'email' => 'khinmarwin@gmail.com', 'role' => Role::CLEANER, 'phone' => '+95950100014', 'nrc' => '12/ThaKaTa(N)100014', 'dob' => '1982-06-11', 'gender' => 'female', 'address' => 'Kyauktada Township, Yangon'],
            ['name' => 'Nilar Aye', 'email' => 'nilaraye@gmail.com', 'role' => Role::CLEANER, 'phone' => '+95950100015', 'nrc' => '12/PaBaTa(N)100015', 'dob' => '1988-09-22', 'gender' => 'female', 'address' => 'Pazundaung Township, Yangon'],
            ['name' => 'Win Win Htet', 'email' => 'winwinhtet@gmail.com', 'role' => Role::CLEANER, 'phone' => '+95950100016', 'nrc' => '12/DaLaNa(N)100016', 'dob' => '1991-01-17', 'gender' => 'female', 'address' => 'Dawbon Township, Yangon'],
            ['name' => 'Mya Mya Oo', 'email' => 'myamyaoo@gmail.com', 'role' => Role::CLEANER, 'phone' => '+95950100017', 'nrc' => '12/KaMaNa(N)100017', 'dob' => '1986-04-28', 'gender' => 'female', 'address' => 'Thaketa Township, Yangon'],
            ['name' => 'Thet Naing Soe', 'email' => 'thetnaingsoe@gmail.com', 'role' => Role::MAINTENANCE_STAFF, 'phone' => '+95950100018', 'nrc' => '12/YaPaTha(N)100018', 'dob' => '1987-08-06', 'gender' => 'male', 'address' => 'Hlaing Tharyar Township, Yangon'],
            ['name' => 'Soe Min Khant', 'email' => 'soeminkhant@gmail.com', 'role' => Role::MAINTENANCE_STAFF, 'phone' => '+95950100019', 'nrc' => '12/TaMaNa(N)100019', 'dob' => '1993-03-19', 'gender' => 'male', 'address' => 'Shwepyitha Township, Yangon'],
            ['name' => 'Kyaw Htet Lin', 'email' => 'kyawhtetlin@gmail.com', 'role' => Role::MAINTENANCE_STAFF, 'phone' => '+95950100020', 'nrc' => '12/PaBaTa(N)100020', 'dob' => '1989-12-02', 'gender' => 'male', 'address' => 'Mingalartaungnyunt Township, Yangon'],
            ['name' => 'Than Zaw Oo', 'email' => 'thanzawoo@gmail.com', 'role' => Role::MAINTENANCE_STAFF, 'phone' => '+95950100021', 'nrc' => '12/DaKaNa(N)100021', 'dob' => '1985-07-14', 'gender' => 'male', 'address' => 'Lanmadaw Township, Yangon'],
            ['name' => 'Min Thu Aung', 'email' => 'minthuaung@gmail.com', 'role' => Role::ELECTRICIAN, 'phone' => '+95950100022', 'nrc' => '12/KaKhaKa(N)100022', 'dob' => '1990-10-08', 'gender' => 'male', 'address' => 'Ahlone Township, Yangon'],
            ['name' => 'Hla Hla Myint', 'email' => 'hlahlamyint@gmail.com', 'role' => Role::ELECTRICIAN, 'phone' => '+95950100023', 'nrc' => '12/PaTaTa(N)100023', 'dob' => '1992-05-24', 'gender' => 'female', 'address' => 'Latha Township, Yangon'],
            ['name' => 'Tun Tun Win', 'email' => 'tuntunwin@gmail.com', 'role' => Role::PLUMBER, 'phone' => '+95950100024', 'nrc' => '12/LaKaNa(N)100024', 'dob' => '1988-02-03', 'gender' => 'male', 'address' => 'Kyimyindaing Township, Yangon'],
            ['name' => 'Aye Chan Moe', 'email' => 'ayechanmoe@gmail.com', 'role' => Role::PLUMBER, 'phone' => '+95950100025', 'nrc' => '12/DaBaNa(N)100025', 'dob' => '1991-11-15', 'gender' => 'female', 'address' => 'Seikgyikanaungto Township, Yangon'],
        ];
    }

    /**
     * @param  array{role: string, name: string, email: string, phone: string, nrc: string, dob: string, gender: string, address: string}  $staffMember
     */
    private function createInactiveStaffWithProfile(int $roleId, array $staffMember): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $staffMember['email']],
            [
                'role_id' => $roleId,
                'name' => $staffMember['name'],
                'password' => Str::password(64),
                'status' => User::STATUS_INACTIVE,
                'must_change_password' => false,
            ],
        );

        if ($user->role_id !== $roleId) {
            $user->update(['role_id' => $roleId]);
        }

        Profile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'phone' => $staffMember['phone'],
                'nrc' => $staffMember['nrc'],
                'dob' => $staffMember['dob'],
                'gender' => $staffMember['gender'],
                'address' => $staffMember['address'],
                'avatar_path' => null,
            ],
        );
    }
}
