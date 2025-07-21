<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class MemberSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // Create test members
        $testMembers = [
            [
                'name' => 'Test Member',
                'email' => 'member@test.com',
                'password' => Hash::make('password123'),
                'phone' => '+1234567890',
                'gender' => 'male',
                'status' => 'active',
                'device_id' => 'test-device-001',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'jane@test.com',
                'password' => Hash::make('password123'),
                'phone' => '+1234567891',
                'gender' => 'female',
                'status' => 'active',
                'device_id' => 'test-device-002',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($testMembers as $memberData)
        {
            Member::create($memberData);
        }

        // Create random members for development
        if (app()->environment('local', 'development'))
        {
            for ($i = 1; $i <= 50; $i++)
            {
                Member::create([
                    'name' => $faker->name,
                    'email' => $faker->unique()->safeEmail,
                    'password' => Hash::make('password123'),
                    'phone' => $faker->phoneNumber,
                    'avatar' => $faker->imageUrl(200, 200, 'people'),
                    'date_of_birth' => $faker->dateTimeBetween('-60 years', '-18 years'),
                    'gender' => $faker->randomElement(['male', 'female']),
                    'status' => $faker->randomElement(['active', 'active', 'active', 'inactive', 'suspended']),
                    'device_id' => strtolower(Str::random(16)),
                    'last_login_at' => $faker->dateTimeThisMonth(),
                    'email_verified_at' => $faker->optional(0.8)->dateTimeThisYear(),
                    'created_at' => $faker->dateTimeBetween('-1 year', 'now'),
                ]);
            }
        }
    }
}
