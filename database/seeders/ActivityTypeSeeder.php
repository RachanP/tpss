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
            ['name' => 'บรรยาย',                    'category' => 'lecture',   'color_code' => '#2563EB'],
            ['name' => 'Mini-class',                'category' => 'lecture',   'color_code' => '#3B82F6'],
            ['name' => 'สัมมนา',                    'category' => 'lecture',   'color_code' => '#60A5FA'],
            ['name' => 'Conference / Case Conference', 'category' => 'lecture', 'color_code' => '#1D4ED8'],
            ['name' => 'Post-Conference',           'category' => 'lecture',   'color_code' => '#1E40AF'],

            // ── ภาคปฏิบัติ (นับเข้า lab_hours) ──────────────────────────
            ['name' => 'Lab / ห้องปฏิบัติการ',     'category' => 'practicum', 'color_code' => '#059669'],
            ['name' => 'กลุ่มย่อย',                 'category' => 'practicum', 'color_code' => '#10B981'],
            ['name' => 'ฝึกปฏิบัติในแหล่งจริง',    'category' => 'practicum', 'color_code' => '#047857'],
            ['name' => 'Round Ward',                'category' => 'practicum', 'color_code' => '#065F46'],
            ['name' => 'รับเคส / สรุปเคส',          'category' => 'practicum', 'color_code' => '#34D399'],
            ['name' => 'Bedside Teaching',          'category' => 'practicum', 'color_code' => '#6EE7B7'],
            ['name' => 'Reflection',                'category' => 'practicum', 'color_code' => '#A7F3D0'],

            // ── อื่นๆ (ไม่นับเข้า quota บรรยาย/แล็ป) ──────────────────
            ['name' => 'ปฐมนิเทศ',                  'category' => 'other',     'color_code' => '#7C3AED'],
            ['name' => 'SDL (Self-Directed Learning)', 'category' => 'other',  'color_code' => '#F59E0B'],
            ['name' => 'Pretest / Posttest',        'category' => 'other',     'color_code' => '#6B7280'],
            ['name' => 'สอบ Procedure',              'category' => 'other',     'color_code' => '#9CA3AF'],
        ];

        foreach ($types as $type) {
            ActivityType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
