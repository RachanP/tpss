<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'setting_key' => 'teaching_quota_weeks',
                'setting_value' => '46',
            ],
            [
                'setting_key' => 'teaching_quota_hours_per_week',
                'setting_value' => '35',
            ],
            [
                'setting_key' => 'teaching_quota_hours',
                'setting_value' => '1610',
            ],
            [
                'setting_key' => 'pa_criteria_config',
                'setting_value' => '{"อาจารย์":{"t":"20-70%","r":"20-70%","s":"5-20%","c":"5-15%","o":"0-20%"},"ผู้ช่วยอาจารย์":{"t":"\u2264 70%","r":"15-20%","s":"5-20%","c":"5-20%","o":"0-20%"},"ผู้ช่วยอาจารย์_\u0e1b\u0e15\u0e23\u0e35":{"t":"30-60%","r":"0%","s":"10-30%","c":"10-20%","o":"0-30%"},"ผู้ช่วยอาจารย์_\u0e04\u0e25\u0e34\u0e19\u0e34\u0e01":{"t":"\u2264 10%","r":"0-5%","s":"70-80%","c":"0-5%","o":"0-10%"},"ผู้ช่วยอาจารย์_\u0e1b\u0e0f\u0e34\u0e1a\u0e31\u0e15\u0e34":{"t":"\u2264 70%","r":"0%","s":"5-20%","c":"5-20%","o":"0-20%"}}',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                ['setting_value' => $setting['setting_value']]
            );
        }
    }
}
