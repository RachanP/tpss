<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Reserved key injected into new_values by the logger.
     * Callers MUST NOT send this key — logger-generated value wins.
     */
    public const CONTEXT_KEY = 'context';

    /**
     * Sensitive fields always masked to '[REDACTED]' before persistence.
     */
    public const MASKED_FIELDS = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
    ];

    /**
     * User-facing Thai category labels.
     * Source of truth for category display in the UI.
     */
    public const CATEGORY_LABELS = [
        'ระบบ'                   => 'ระบบ',
        'ตารางสอน'               => 'ตารางสอน',
        'การอนุมัติ'              => 'การอนุมัติ',
        'ข้อมูลหลัก'             => 'ข้อมูลหลัก',
        'รายวิชาและผู้รับผิดชอบ'  => 'รายวิชาและผู้รับผิดชอบ',
        'ตั้งค่าระบบ'            => 'ตั้งค่าระบบ',
        'ผู้ใช้และสิทธิ์'         => 'ผู้ใช้และสิทธิ์',
        'รายงาน'                 => 'รายงาน',
    ];

    /**
     * User-facing action labels available in the audit log filter.
     * Keep this independent from existing database rows so fresh installs
     * still show the actions the system can create.
     */
    public const ACTION_FILTER_LABELS = [
        'เข้าสู่ระบบ',
        'ออกจากระบบ',
        'เปลี่ยนรหัสผ่าน',
        'สร้าง',
        'แก้ไข',
        'ลบ',
        'เปลี่ยนสถานะ',
        'นำเข้า CSV',
        'คัดลอก',
        'เปิดช่วงจัดตาราง',
        'ปิดช่วงจัดตาราง',
        'ซิงก์ข้อมูล',
    ];

    /**
     * Auto-generated fallback descriptions per action verb.
     */
    private const ACTION_DESCRIPTIONS = [
        'เข้าสู่ระบบ'       => 'เข้าสู่ระบบ',
        'ออกจากระบบ'       => 'ออกจากระบบ',
        'เปลี่ยนบทบาท'      => 'เปลี่ยนบทบาทการใช้งาน',
        'สร้าง'            => 'สร้างข้อมูลใหม่',
        'แก้ไข'            => 'แก้ไขข้อมูล',
        'ลบ'               => 'ลบข้อมูล',
        'อนุมัติ'           => 'อนุมัติรายการ',
        'ปฏิเสธ'           => 'ปฏิเสธรายการ',
        'ส่งกลับแก้ไข'      => 'ส่งกลับให้แก้ไข',
        'ส่ง'              => 'ส่งขออนุมัติ',
        'ซิงก์ข้อมูล'       => 'ซิงก์ข้อมูลรายวิชา',
        'ล็อกแม่แบบ'        => 'ล็อกแม่แบบรายวิชา',
        'เพิ่มอาจารย์'      => 'เพิ่มอาจารย์เข้ารายวิชา',
        'ลบอาจารย์'        => 'ลบอาจารย์ออกจากรายวิชา',
        'ลบหลายรายการ'     => 'ลบหลายรายการพร้อมกัน',
        'เปลี่ยนสถานะ'      => 'เปลี่ยนสถานะข้อมูล',
        'เปิดช่วงจัดตาราง'  => 'เปิดช่วงเวลาจัดตารางสอน',
        'ปิดช่วงจัดตาราง'   => 'ปิดช่วงเวลาจัดตารางสอน',
        'ส่งออก PDF'       => 'ส่งออกรายงาน PDF',
        'ส่งออก Excel'     => 'ส่งออกรายงาน Excel',
        'เพิ่มสิทธิ์'       => 'เพิ่มสิทธิ์การใช้งาน',
        'ลบสิทธิ์'          => 'ลบสิทธิ์การใช้งาน',
        'ปิดใช้งาน'         => 'ปิดการใช้งานบัญชี',
    ];

    /**
     * Record an auditable event.
     *
     * @param string      $action      Thai action string e.g. 'ตารางสอน.แก้ไข'
     * @param string      $table       DB table name e.g. 'schedules'
     * @param int         $recordId    PK of the affected row
     * @param array|null  $oldValues   Only changed fields (before). Null for create.
     * @param array|null  $newValues   Only changed fields (after). Null for delete.
     *                                 DO NOT include 'context' key — logger injects it.
     * @param string|null $category    Thai category; auto-inferred from action prefix if null
     * @param string|null $description Thai display text; auto-generated if null
     */
    public static function log(
        string $action,
        string $table,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $category = null,
        ?string $description = null,
    ): ?AuditLog {
        try {
            $category    ??= Str::before($action, '.');
            $description ??= self::generateDescription($action);

            // Auto-inject request context — logger-generated wins; caller cannot override
            $newValues = array_merge($newValues ?? [], [
                self::CONTEXT_KEY => [
                    'ip_address' => request()->ip(),
                    'user_agent' => substr(request()->userAgent() ?? '', 0, 200),
                ],
            ]);

            return AuditLog::create([
                'user_id'        => auth()->id(),
                'action'         => $action,
                'table_affected' => $table,
                'record_id'      => $recordId,
                'old_values'     => self::sanitize($oldValues),
                'new_values'     => self::sanitize($newValues),
                'category'       => $category,
                'description'    => $description,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Diff two arrays — return only the keys that changed.
     *
     * @return array{old: array, new: array}
     */
    public static function diff(array $before, array $after): array
    {
        $changedKeys = array_keys(array_filter(
            $after,
            fn($v, $k) => !array_key_exists($k, $before) || $before[$k] !== $v,
            ARRAY_FILTER_USE_BOTH,
        ));

        $old = array_intersect_key($before, array_flip($changedKeys));
        $new = array_intersect_key($after,  array_flip($changedKeys));

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Sanitize an array — replace MASKED_FIELDS values with '[REDACTED]'.
     */
    public static function sanitize(?array $data): ?array
    {
        if ($data === null) return null;

        foreach (self::MASKED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '[REDACTED]';
            }
        }
        return $data;
    }

    /**
     * Sanitize and also report which fields were masked.
     *
     * @return array{0: array|null, 1: string[]}
     */
    public static function sanitizeWithReport(?array $data): array
    {
        if ($data === null) return [null, []];

        $masked = [];
        foreach (self::MASKED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '[REDACTED]';
                $masked[]     = $field;
            }
        }
        return [$data, $masked];
    }

    /**
     * Auto-generate a human-readable Thai description from the action string.
     * Format: '{category}.{verb}' e.g. 'ตารางสอน.แก้ไข'
     */
    private static function generateDescription(string $action): string
    {
        $category = Str::before($action, '.');
        $verb     = Str::after($action, '.');

        $verbDesc = self::ACTION_DESCRIPTIONS[$verb] ?? $verb;

        return "{$verbDesc} ({$category})";
    }
}
