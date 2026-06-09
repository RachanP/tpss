<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * ครอบ Bug ข้อ 3 — validation ต้องแสดงเป็นภาษาไทย (locale = th)
 */
class LocalizationTest extends TestCase
{
    public function test_app_locale_is_thai(): void
    {
        $this->assertSame('th', app()->getLocale());
    }

    public function test_required_rule_renders_thai_message(): void
    {
        $validator = Validator::make([], ['name' => 'required']);

        $this->assertStringContainsString('จำเป็นต้องกรอก', $validator->errors()->first('name'));
        // ต้องไม่หลุดข้อความอังกฤษ default
        $this->assertStringNotContainsString('field is required', $validator->errors()->first('name'));
    }

    public function test_attribute_names_are_localized(): void
    {
        $validator = Validator::make([], [
            'student_group_ids' => 'required',
            'username'          => 'required',
        ]);

        $this->assertStringContainsString('กลุ่มนักศึกษา', $validator->errors()->first('student_group_ids'));
        $this->assertStringContainsString('ชื่อผู้ใช้งาน', $validator->errors()->first('username'));
    }
}
