<x-app-layout title="ภาพรวม — อาจารย์">
    <div style="padding: 2rem;">
        <div style="display: flex; gap: 24px; margin-bottom: 32px;">
            <div style="flex: 1; padding: 24px; background: white; border-radius: 12px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px;">
                <div style="font-size: 14px; font-weight: 600; color: var(--fg-2);">เกณฑ์ภาระงานสอนของคุณ</div>
                @if($quota !== null)
                    <div style="display: flex; align-items: baseline; gap: 8px;">
                        <div style="font-size: 36px; font-weight: 800; color: var(--brand-navy); line-height: 1;">
                            {{ number_format($quota, 1) }}
                        </div>
                        <div style="font-size: 14px; color: var(--fg-3); font-weight: 500;">
                            ชั่วโมงทำการ / {{ $period }}
                        </div>
                    </div>
                @else
                    <div style="font-size: 14px; color: var(--fg-3); font-style: italic;">
                        - ไม่ระบุเกณฑ์ภาระงานสอน -
                    </div>
                @endif
            </div>
            
            <div style="flex: 1; padding: 24px; background: white; border-radius: 12px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px;">
                <div style="font-size: 14px; font-weight: 600; color: var(--fg-2);">ชั่วโมงสอนสะสม (เทอมปัจจุบัน)</div>
                <div style="display: flex; align-items: baseline; gap: 8px;">
                    <div style="font-size: 36px; font-weight: 800; color: var(--fg-3); line-height: 1;">
                        0.0
                    </div>
                    <div style="font-size: 14px; color: var(--fg-3); font-weight: 500;">
                        ชั่วโมงทำการ
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align: center; color: var(--fg-3); margin-top: 48px;">
            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 16px; color: var(--fg-3);">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--fg-1);">ตารางสอนของฉัน</div>
            <div style="font-size: 0.9rem; margin-top: 0.5rem;">หน้านี้อยู่ระหว่างพัฒนา</div>
        </div>
    </div>
</x-app-layout>
