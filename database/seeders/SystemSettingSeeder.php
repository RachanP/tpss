<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SystemSetting::set(
            'teaching_quota_hours', 
            '1610', 
            'จำนวนชั่วโมงปฏิบัติงานรวมใน 1 ปี (ใช้คำนวณภาระงานสอน)'
        );
    }
}
