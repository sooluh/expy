<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin'),
                'name' => 'Admin User',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($data as $user) {
            User::firstOrCreate(['email' => $user['email']], $user);
        }
    }
}
