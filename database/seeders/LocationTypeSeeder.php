<?php

namespace Database\Seeders;

use App\Models\LocationType;
use Illuminate\Database\Seeder;

class LocationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'ห้องเรียนทั่วไป',
            'ห้องปฏิบัติการ',
            'หอผู้ป่วย',
            'โรงพยาบาล',
            'ศูนย์พัฒนาเด็กก่อนวัยเรียน',
            'โรงเรียน',
            'ชุมชน',
            'โรงพยาบาลส่งเสริมสุขภาพตำบล',
            'ห้องคลอด',
            'ออนไลน์',

        ];

        foreach ($types as $type) {
            LocationType::firstOrCreate(['name' => $type]);
        }
    }
}
