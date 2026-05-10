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
                'name' => 'System Admin',
                'email' => 'admin_01@mahidol.edu',
                'role' => 'admin'
            ],
            [
                'username' => 'staff_01',
                'name' => 'Support Staff',
                'email' => 'staff_01@mahidol.edu',
                'role' => 'staff'
            ],
            [
                'username' => 'maker_01',
                'name' => 'Course Head',
                'email' => 'maker_01@mahidol.edu',
                'role' => 'course_head'
            ],
            [
                'username' => 'approver_01',
                'name' => 'Executive',
                'email' => 'approver_01@mahidol.edu',
                'role' => 'executive'
            ],
            [
                'username' => 'lecturer_01',
                'name' => 'Instructor',
                'email' => 'lecturer_01@mahidol.edu',
                'role' => 'instructor'
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
