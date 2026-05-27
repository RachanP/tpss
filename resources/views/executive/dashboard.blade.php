<x-app-layout title="รออนุมัติ — ผู้บริหาร">
    <div style="padding: 2rem; display: grid; gap: 16px; max-width: 760px; margin: 0 auto;">
        @include('shared.dashboard.conflict_summary')

        <div style="text-align: center; color: var(--fg-3);">
            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 16px; color: var(--fg-3);">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--fg-1);">คิวอนุมัติ</div>
            <div style="font-size: 0.9rem; margin-top: 0.5rem;">หน้านี้อยู่ระหว่างพัฒนา</div>
        </div>
    </div>
</x-app-layout>
