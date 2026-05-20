@php
    $course = $courseOffering->course;
@endphp

<x-app-layout title="แก้ไขรายการสอน">
    <div style="margin-bottom:18px;">
        <a href="{{ route('maker.course_offerings.schedules.index', $courseOffering) }}" class="body-sm" style="color:var(--brand-navy);text-decoration:none;">← กลับไปตารางสอน</a>
        <div class="eyebrow" style="margin-top:10px;">แก้ไขรายการสอน</div>
        <h1 class="h1" style="margin:4px 0 6px;">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</h1>
    </div>

    @include('course_head.schedules._form', [
        'action' => route('maker.course_offerings.schedules.update', [$courseOffering, $schedule]),
        'method' => 'PUT',
        'submitLabel' => 'บันทึกการแก้ไข',
        'schedule' => $schedule,
    ])
</x-app-layout>
