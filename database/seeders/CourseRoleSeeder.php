<?php

namespace Database\Seeders;

use App\Models\CourseRole;
use Illuminate\Database\Seeder;

class CourseRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name_th' => 'หัวหน้าวิชา',        'sort_order' => 1],
            ['name_th' => 'เลขานุการวิชา',       'sort_order' => 2],
            ['name_th' => 'อาจารย์ผู้สอน',       'sort_order' => 3],
            ['name_th' => 'อาจารย์ประจำกลุ่ม',  'sort_order' => 4],
            ['name_th' => 'อาจารย์พี่เลี้ยง',   'sort_order' => 5],
        ];

        foreach ($roles as $role) {
            CourseRole::updateOrCreate(['name_th' => $role['name_th']], $role);
        }
    }
}
