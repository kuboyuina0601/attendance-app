<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'user1@example.com'],
            [
                'name' => 'ユーザー１(一般)',
                'password' => 'password',
                'email_verified_at' => now(),
                'admin_flag' => 0,
            ]
        );

        User::updateOrCreate(
            ['email' => 'user2@example.com'],
            [
                'name' => 'ユーザー２(一般)',
                'password' => 'password',
                'email_verified_at' => now(),
                'admin_flag' => 0,
            ]
        );

        User::updateOrCreate(
            ['email' => 'user3@example.com'],
            [
                'name' => 'ユーザー３(管理者)',
                'password' => 'password',
                'email_verified_at' => now(),
                'admin_flag' => 1,
            ]
        );
    }
}