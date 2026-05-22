<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // ── ภาคทฤษฎี (นับเข้า lecture_hours) ────────────────────────
            ['name' => 'บรรยาย',                    'category' => 'lecture',   'color_code' => '#2563EB'],  // น้ำเงิน
            ['name' => 'Mini-class',                'category' => 'lecture',   'color_code' => '#0891B2'],  // Cyan/Teal
            ['name' => 'สัมมนา',                    'category' => 'lecture',   'color_code' => '#7C3AED'],  // ม่วง
            ['name' => 'Conference / Case Conference', 'category' => 'lecture', 'color_code' => '#0D9488'], // Teal เข้ม
            ['name' => 'Post-Conference',           'category' => 'lecture',   'color_code' => '#6366F1'],  // Indigo

            // ── ภาคปฏิบัติ (นับเข้า lab_hours) ──────────────────────────
            ['name' => 'Lab / ห้องปฏิบัติการ',     'category' => 'practicum', 'color_code' => '#059669'],  // เขียวเข้ม
            ['name' => 'กลุ่มย่อย',                 'category' => 'practicum', 'color_code' => '#D97706'],  // Amber เข้ม
            ['name' => 'ฝึกปฏิบัติในแหล่งจริง',    'category' => 'practicum', 'color_code' => '#DC2626'],  // แดง
            ['name' => 'Round Ward',                'category' => 'practicum', 'color_code' => '#9333EA'],  // ม่วงสด
            ['name' => 'รับเคส / สรุปเคส',          'category' => 'practicum', 'color_code' => '#2DD4BF'],  // Teal อ่อน
            ['name' => 'Bedside Teaching',          'category' => 'practicum', 'color_code' => '#E11D48'],  // Rose
            ['name' => 'Reflection',                'category' => 'practicum', 'color_code' => '#8B5CF6'],  // Violet

            // ── อื่นๆ (ไม่นับเข้า quota บรรยาย/แล็ป) ──────────────────
            ['name' => 'ปฐมนิเทศ',                  'category' => 'other',     'color_code' => '#EA580C'],  // ส้ม
            ['name' => 'SDL (Self-Directed Learning)', 'category' => 'other',  'color_code' => '#CA8A04'],  // เหลืองทอง
            ['name' => 'Pretest / Posttest',        'category' => 'other',     'color_code' => '#4B5563'],  // เทาเข้ม
            ['name' => 'สอบ Procedure',              'category' => 'other',     'color_code' => '#B91C1C'],  // แดงเข้ม
        ];

        foreach ($types as $type) {
            ActivityType::updateOrCreate(
                ['name' => $type['name']],
                ['category' => $type['category'], 'color_code' => $type['color_code']]
            );
        }
    }
}
