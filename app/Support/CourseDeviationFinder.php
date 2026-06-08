<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * หาวิชาที่ "ผู้สอน/รายละเอียดใน offering ต่างจากแม่แบบ (template)"
 * ใช้ร่วมระหว่างหน้าแจ้งเตือน (section รายงาน) และ badge สรุป
 */
class CourseDeviationFinder
{
    /**
     * @return Collection<int,Course> วิชาที่มี deviation (eager-load สำหรับแสดงผล)
     */
    public static function coursesWithDeviation(): Collection
    {
        $courses = Course::with([
            'instructors.instructorProfile.department',
            'curriculum',
            'department',
        ])
            ->whereHas('courseOfferings', fn ($q) => $q->whereHas(
                'academicYear',
                fn ($y) => $y->whereIn('phase', ['scheduling', 'published'])
            ))
            ->orderBy('course_code')
            ->get();

        if ($courses->isEmpty()) {
            return collect();
        }

        $offerings = CourseOffering::with(['instructorPool', 'academicYear'])
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereHas('academicYear', fn ($q) => $q->whereIn('phase', ['scheduling', 'published']))
            ->get()
            ->groupBy('course_id');

        return $courses->filter(function (Course $course) use ($offerings) {
            foreach ($offerings[$course->id] ?? [] as $offering) {
                $instructorDiff = $course->instructorPoolDeviationFor($offering);
                $hasAny = count($instructorDiff['added']) + count($instructorDiff['removed'])
                    + count($instructorDiff['role_changed']);
                if ($hasAny > 0) {
                    return true;
                }
            }
            return false;
        })->values();
    }

    /**
     * เหมือน coursesWithDeviation แต่คืน "รายละเอียดพร้อมแสดงผล" ต่อวิชา/ต่อ offering
     * (ชื่ออาจารย์ + บทบาท + เหตุผล instructor_pool_note) — ใช้กางในหน้าแจ้งเตือน admin
     *
     * @return Collection<int, array{course: Course, offerings: array<int, array<string, mixed>>}>
     */
    public static function detailed(): Collection
    {
        $courses = Course::with([
            'instructors.instructorProfile.department',
            'curriculum',
            'department',
        ])
            ->whereHas('courseOfferings', fn ($q) => $q->whereHas(
                'academicYear',
                fn ($y) => $y->whereIn('phase', ['scheduling', 'published'])
            ))
            ->orderBy('course_code')
            ->get();

        if ($courses->isEmpty()) {
            return collect();
        }

        $offerings = CourseOffering::with(['instructorPool', 'academicYear'])
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereHas('academicYear', fn ($q) => $q->whereIn('phase', ['scheduling', 'published']))
            ->get()
            ->groupBy('course_id');

        // เก็บ raw diff ต่อ offering + รวบรวม user id ทั้งหมดเพื่อโหลดชื่อทีเดียว
        $userIds = collect();
        $raw = $courses->map(function (Course $course) use ($offerings, $userIds) {
            $offeringDiffs = collect($offerings[$course->id] ?? [])
                ->map(function (CourseOffering $offering) use ($course, $userIds) {
                    $diff = $course->instructorPoolDeviationFor($offering);
                    foreach (['added', 'removed'] as $bucket) {
                        foreach ($diff[$bucket] as $e) {
                            $userIds->push($e['user_id']);
                        }
                    }
                    foreach ($diff['role_changed'] as $e) {
                        $userIds->push($e['user_id']);
                    }

                    return ['offering' => $offering, 'diff' => $diff];
                })
                ->filter(fn ($d) => count($d['diff']['added']) + count($d['diff']['removed']) + count($d['diff']['role_changed']) > 0)
                ->values();

            return ['course' => $course, 'offerings' => $offeringDiffs];
        })->filter(fn ($c) => $c['offerings']->isNotEmpty())->values();

        $users = User::whereIn('id', $userIds->unique()->values())
            ->with('instructorProfile')
            ->get()
            ->keyBy('id');
        $roles = CourseRole::pluck('name_th', 'id');

        $nameOf = fn ($id) => $users[$id]?->formatted_name ?? ('#' . $id);
        $roleOf = fn ($id) => $id ? ($roles[$id] ?? '-') : 'ไม่ระบุบทบาท';

        return $raw->map(fn ($c) => [
            'course' => $c['course'],
            'offerings' => $c['offerings']->map(fn ($d) => [
                'offering' => $d['offering'],
                'note' => $d['offering']->instructor_pool_note,
                'added' => collect($d['diff']['added'])
                    ->map(fn ($e) => ['name' => $nameOf($e['user_id']), 'role' => $roleOf($e['role_id'])])->all(),
                'removed' => collect($d['diff']['removed'])
                    ->map(fn ($e) => ['name' => $nameOf($e['user_id']), 'role' => $roleOf($e['role_id'])])->all(),
                'role_changed' => collect($d['diff']['role_changed'])
                    ->map(fn ($e) => ['name' => $nameOf($e['user_id']), 'from' => $roleOf($e['template_role_id']), 'to' => $roleOf($e['offering_role_id'])])->all(),
            ])->all(),
        ]);
    }
}
