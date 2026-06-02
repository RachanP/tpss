<x-app-layout title="รออนุมัติ — ผู้บริหาร">
    <div class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => 'ภาพรวม / ผู้บริหาร',
            'title'  => 'คิวอนุมัติ',
            'desc'   => 'ตรวจสอบภาระงานและการชนของตารางสอน เพื่ออนุมัติหรือตีกลับรายวิชาที่ส่งเข้ามา',
        ])

        @include('shared.dashboard.conflict_summary')

        @include('shared.dashboard.role_empty', [
            'icon'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'title' => 'คิวอนุมัติ',
            'desc'  => 'หน้านี้อยู่ระหว่างพัฒนา',
        ])
    </div>
</x-app-layout>
