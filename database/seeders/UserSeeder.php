<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            [
                'username' => 'admin_01',
                'name' => 'ราชันย์ พิพัฒน์',
                'email' => 'admin_01@mahidol.edu',
                'role' => 'admin'
            ]
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'username' => $userData['username'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $password,
                'is_active' => true,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role' => $userData['role'],
                'is_primary' => true,
            ]);
        }
    }
}
