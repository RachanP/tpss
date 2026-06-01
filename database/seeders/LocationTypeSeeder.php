<?php

namespace Database\Seeders;

use App\Models\LocationType;
use Illuminate\Database\Seeder;

class LocationTypeSeeder extends Seeder
{
    public function run(): void
    {
        // is_shared = true → สถานที่ขนาดใหญ่/ภายนอกที่หลายวิชาใช้ร่วมกันได้ (ไม่เช็ค room conflict + ไม่เตือน capacity)
        $types = [
            ['name' => 'ห้องเรียนทั่วไป',                   'is_shared' => false],
            ['name' => 'ห้องปฏิบัติการ',                    'is_shared' => false],
            ['name' => 'หอผู้ป่วย',                          'is_shared' => true],
            ['name' => 'โรงพยาบาล',                          'is_shared' => true],
            ['name' => 'ศูนย์พัฒนาเด็กก่อนวัยเรียน',          'is_shared' => true],
            ['name' => 'โรงเรียน',                           'is_shared' => true],
            ['name' => 'ชุมชน',                              'is_shared' => true],
            ['name' => 'โรงพยาบาลส่งเสริมสุขภาพตำบล',         'is_shared' => true],
            ['name' => 'ห้องคลอด',                           'is_shared' => false],
            ['name' => 'ออนไลน์',                            'is_shared' => false],
        ];

        foreach ($types as $type) {
            LocationType::updateOrCreate(['name' => $type['name']], ['is_shared' => $type['is_shared']]);
        }
    }
}
