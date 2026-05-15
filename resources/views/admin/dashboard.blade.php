<x-app-layout title="ภาพรวม — ผู้ดูแลระบบ">
    <div style="padding: 2rem;">
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--brand-navy);">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--fg-1);">ภาพรวมผู้ดูแลระบบ</div>
            </div>
            <div style="color: var(--fg-3);">แสดงข้อมูลสรุปสถานะการทำงานและภาระงานสอนของอาจารย์ทั้งหมด</div>
        </div>

        @include('shared.dashboard.master_data_alerts')
        @include('shared.dashboard.instructors_workload')
    </div>
</x-app-layout>
