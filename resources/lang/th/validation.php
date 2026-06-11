<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => ':attribute ต้องได้รับการยอมรับ',
    'active_url' => ':attribute ต้องเป็น URL ที่ถูกต้อง',
    'after' => ':attribute ต้องเป็นวันที่หลัง :date',
    'after_or_equal' => ':attribute ต้องเป็นวันที่เท่ากับหรือหลัง :date',
    'alpha' => ':attribute ต้องเป็นตัวอักษรเท่านั้น',
    'alpha_dash' => ':attribute ต้องเป็นตัวอักษร ตัวเลข ขีดกลาง หรือขีดล่าง',
    'alpha_num' => ':attribute ต้องเป็นตัวอักษรหรือตัวเลข',
    'array' => ':attribute ต้องเป็นอาเรย์',
    'before' => ':attribute ต้องเป็นวันที่ก่อน :date',
    'before_or_equal' => ':attribute ต้องเป็นวันที่เท่ากับหรือก่อน :date',
    'between' => [
        'numeric' => ':attribute ต้องอยู่ระหว่าง :min และ :max',
        'file' => ':attribute ต้องอยู่ระหว่าง :min และ :max กิโลไบต์',
        'string' => ':attribute ต้องอยู่ระหว่าง :min และ :max ตัวอักษร',
        'array' => ':attribute ต้องมีระหว่าง :min และ :max รายการ',
    ],
    'boolean' => ':attribute ต้องเป็น true หรือ false',
    'confirmed' => ':attribute ยืนยันไม่ตรงกัน',
    'date' => ':attribute ไม่ใช่วันที่ที่ถูกต้อง',
    'date_format' => ':attribute ไม่ตรงกับรูปแบบ :format',
    'different' => ':attribute และ :other ต้องไม่เหมือนกัน',
    'digits' => ':attribute ต้องเป็นตัวเลข :digits หลัก',
    'digits_between' => ':attribute ต้องอยู่ระหว่าง :min และ :max หลัก',
    'email' => ':attribute ต้องเป็นที่อยู่อีเมลที่ถูกต้อง',
    'exists' => 'ที่เลือกของ :attribute ไม่ถูกต้อง',
    'filled' => ':attribute ต้องมีค่า',
    'image' => ':attribute ต้องเป็นรูปภาพ',
    'in' => 'ที่เลือกของ :attribute ไม่ถูกต้อง',
    'integer' => ':attribute ต้องเป็นจำนวนเต็ม',
    'ip' => ':attribute ต้องเป็นที่อยู่ IP ที่ถูกต้อง',
    'json' => ':attribute ต้องเป็นสตริง JSON ที่ถูกต้อง',
    'max' => [
        'numeric' => ':attribute ต้องไม่เกิน :max',
        'file' => ':attribute ต้องไม่เกิน :max กิโลไบต์',
        'string' => ':attribute ต้องไม่เกิน :max ตัวอักษร',
        'array' => ':attribute ต้องไม่เกิน :max รายการ',
    ],
    'mimes' => ':attribute ต้องเป็นไฟล์ประเภท: :values',
    'min' => [
        'numeric' => ':attribute ต้องมีค่าอย่างน้อย :min',
        'file' => ':attribute ต้องมีขนาดอย่างน้อย :min กิโลไบต์',
        'string' => ':attribute ต้องมีความยาวอย่างน้อย :min ตัวอักษร',
        'array' => ':attribute ต้องมีอย่างน้อย :min รายการ',
    ],
    'not_in' => 'ที่เลือกของ :attribute ไม่ถูกต้อง',
    'numeric' => ':attribute ต้องเป็นตัวเลข',
    'present' => ':attribute ต้องอยู่ในข้อมูล',
    'required' => ':attribute จำเป็นต้องกรอก',
    'required_if' => ':attribute จำเป็นต้องกรอกเมื่อ :other เป็น :value',
    'same' => ':attribute และ :other ต้องตรงกัน',
    'size' => [
        'numeric' => ':attribute ต้องมีค่า :size',
        'file' => ':attribute ต้องมีขนาด :size กิโลไบต์',
        'string' => ':attribute ต้องยาว :size ตัวอักษร',
        'array' => ':attribute ต้องประกอบด้วย :size รายการ',
    ],
    'string' => ':attribute ต้องเป็นสตริง',
    'timezone' => ':attribute ต้องเป็นเขตเวลาที่ถูกต้อง',
    'unique' => ':attribute ถูกใช้ไปแล้ว',
    'url' => ':attribute รูปแบบไม่ถูกต้อง',

    /* Custom validation lines */
    'custom' => [
        'start_time' => [
            'before' => 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่มต้น',
        ],
    ],

    /* Attribute names — ชื่อ field ภาษาไทย เพื่อให้ข้อความ validation อ่านเป็นธรรมชาติ */
    'attributes' => [
        // ผู้ใช้และสิทธิ์
        'username' => 'ชื่อผู้ใช้งาน',
        'name' => 'ชื่อ-นามสกุล',
        'email' => 'อีเมล',
        'password' => 'รหัสผ่าน',
        'password_confirmation' => 'ยืนยันรหัสผ่าน',
        'employee_id' => 'รหัสพนักงาน',
        'prefix' => 'คำนำหน้า',
        'roles' => 'บทบาท',
        'roles.*' => 'บทบาท',
        'primary_role' => 'บทบาทหลัก',
        'is_active' => 'สถานะการใช้งาน',
        'role' => 'บทบาท',

        // ข้อมูลหลัก — หลักสูตร/รายวิชา/ภาควิชา
        'department_id' => 'ภาควิชา',
        'curriculum_id' => 'หลักสูตร',
        'course_id' => 'รายวิชา',
        'course_code' => 'รหัสวิชา',
        'name_th' => 'ชื่อ (ไทย)',
        'name_en' => 'ชื่อ (อังกฤษ)',
        'credits' => 'หน่วยกิต',
        'education_level' => 'ระดับการศึกษา',
        'duration_years' => 'จำนวนปีของหลักสูตร',
        'total_credits_required' => 'หน่วยกิตขั้นต่ำ',
        'default_year_level' => 'ชั้นปี',
        'is_required' => 'ประเภทวิชา (บังคับ/เลือก)',
        'prerequisite_ids' => 'วิชาบังคับก่อน',

        // ห้อง/สถานที่/ประเภทกิจกรรม
        'room_id' => 'ห้อง/สถานที่',
        'location_type_id' => 'ประเภทสถานที่',
        'capacity' => 'ความจุ',
        'building' => 'อาคาร',
        'activity_type_id' => 'ประเภทกิจกรรม',
        'category' => 'หมวดหมู่',

        // ปีการศึกษา/ปฏิทิน/วันหยุด
        'date' => 'วันที่',
        'start_date' => 'วันที่เริ่ม',
        'end_date' => 'วันที่สิ้นสุด',
        'remark' => 'หมายเหตุ',

        // ตารางสอน (slot)
        'start_time' => 'เวลาเริ่ม',
        'end_time' => 'เวลาสิ้นสุด',
        'course_offering_id' => 'รอบการสอน',
        'topic' => 'หัวข้อกิจกรรม',
        'capacity_required' => 'จำนวนรองรับ',
        'sub_group_label' => 'ป้ายกลุ่มย่อย',
        'lead_instructor_id' => 'ผู้สอนหลัก',
        'instructor_ids' => 'ผู้สอน',
        'instructor_ids.*' => 'ผู้สอน',
        'student_group_ids' => 'กลุ่มนักศึกษา',
        'student_group_ids.*' => 'กลุ่มนักศึกษา',
    ],
];
