<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use App\Models\StudentGroup;
use Illuminate\Database\Seeder;

class StudentGroupSeeder extends Seeder
{
    public function run(): void
    {
        $curriculum = Curriculum::where('is_active', true)->first();
        if (!$curriculum) {
            $this->command->warn('ไม่พบหลักสูตรที่ active — ข้ามการ seed กลุ่มนักศึกษา');
            return;
        }

        $groups = [
            // ── ชั้นปีที่ 1 ── รหัส A (เริ่มเรียน 2568)
            ['group_code' => 'A1', 'year_level' => 1, 'student_count' => 42, 'color_code' => '#3B82F6'],
            ['group_code' => 'A2', 'year_level' => 1, 'student_count' => 42, 'color_code' => '#3B82F6'],
            ['group_code' => 'A3', 'year_level' => 1, 'student_count' => 42, 'color_code' => '#3B82F6'],
            ['group_code' => 'A4', 'year_level' => 1, 'student_count' => 42, 'color_code' => '#3B82F6'],
            ['group_code' => 'A5', 'year_level' => 1, 'student_count' => 42, 'color_code' => '#3B82F6'],
            ['group_code' => 'A6', 'year_level' => 1, 'student_count' => 42, 'color_code' => '#3B82F6'],

            // ── ชั้นปีที่ 2 ── รหัส B
            ['group_code' => 'B1', 'year_level' => 2, 'student_count' => 40, 'color_code' => '#10B981'],
            ['group_code' => 'B2', 'year_level' => 2, 'student_count' => 40, 'color_code' => '#10B981'],
            ['group_code' => 'B3', 'year_level' => 2, 'student_count' => 40, 'color_code' => '#10B981'],
            ['group_code' => 'B4', 'year_level' => 2, 'student_count' => 40, 'color_code' => '#10B981'],
            ['group_code' => 'B5', 'year_level' => 2, 'student_count' => 40, 'color_code' => '#10B981'],
            ['group_code' => 'B6', 'year_level' => 2, 'student_count' => 40, 'color_code' => '#10B981'],

            // ── ชั้นปีที่ 3 ── รหัส C
            ['group_code' => 'C1', 'year_level' => 3, 'student_count' => 38, 'color_code' => '#F59E0B'],
            ['group_code' => 'C2', 'year_level' => 3, 'student_count' => 38, 'color_code' => '#F59E0B'],
            ['group_code' => 'C3', 'year_level' => 3, 'student_count' => 38, 'color_code' => '#F59E0B'],
            ['group_code' => 'C4', 'year_level' => 3, 'student_count' => 38, 'color_code' => '#F59E0B'],
            ['group_code' => 'C5', 'year_level' => 3, 'student_count' => 38, 'color_code' => '#F59E0B'],
            ['group_code' => 'C6', 'year_level' => 3, 'student_count' => 38, 'color_code' => '#F59E0B'],

            // ── ชั้นปีที่ 4 ── รหัส D
            ['group_code' => 'D1', 'year_level' => 4, 'student_count' => 35, 'color_code' => '#8B5CF6'],
            ['group_code' => 'D2', 'year_level' => 4, 'student_count' => 35, 'color_code' => '#8B5CF6'],
            ['group_code' => 'D3', 'year_level' => 4, 'student_count' => 35, 'color_code' => '#8B5CF6'],
            ['group_code' => 'D4', 'year_level' => 4, 'student_count' => 35, 'color_code' => '#8B5CF6'],
            ['group_code' => 'D5', 'year_level' => 4, 'student_count' => 35, 'color_code' => '#8B5CF6'],
            ['group_code' => 'D6', 'year_level' => 4, 'student_count' => 35, 'color_code' => '#8B5CF6'],
        ];

        foreach ($groups as $group) {
            StudentGroup::firstOrCreate(
                ['group_code' => $group['group_code']],
                array_merge($group, ['curriculum_id' => $curriculum->id])
            );
        }

        $this->command->info('StudentGroupSeeder: สร้าง ' . count($groups) . ' กลุ่ม (ปี 1–4, A–D)');
    }
}
