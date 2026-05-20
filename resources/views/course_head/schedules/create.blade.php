@php
    $course = $courseOffering->course;
    $isGlobalCreate = (bool) ($isGlobalCreate ?? false);
    $backUrl = $isGlobalCreate
        ? route('maker.schedules.index', array_filter(['week_start' => $weekStart ?? null]))
        : route('maker.course_offerings.schedules.index', $courseOffering);
@endphp

<x-app-layout title="เพิ่มรายการสอน">
    <div style="margin-bottom:18px;">
        <a href="{{ $backUrl }}" class="body-sm" style="color:var(--brand-navy);text-decoration:none;">← กลับไปตารางสอน</a>
        <div class="eyebrow" style="margin-top:10px;">รายการสอนใหม่</div>
        <h1 class="h1" style="margin:4px 0 6px;">{{ $isGlobalCreate ? 'เพิ่มกิจกรรมในตารางรวม' : (($course?->course_code ?? '-') . ' ' . ($course?->name_th ?? $course?->name_en ?? '')) }}</h1>
    </div>

    @include('course_head.schedules._form', [
        'action' => $isGlobalCreate ? route('maker.schedules.store') : route('maker.course_offerings.schedules.store', $courseOffering),
        'method' => 'POST',
        'submitLabel' => 'บันทึกรายการสอน',
        'backUrl' => $backUrl,
        'isGlobalCreate' => $isGlobalCreate,
        'availableOfferings' => $availableOfferings ?? collect(),
        'weekStart' => $weekStart ?? null,
    ])
</x-app-layout>
