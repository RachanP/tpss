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

    /* Attribute names */
    'attributes' => [
        'start_time' => 'เวลาเริ่มต้น',
        'end_time' => 'เวลาสิ้นสุด',
        'course_offering_id' => 'รอบการสอน',
        'room_id' => 'ห้อง',
    ],
];
