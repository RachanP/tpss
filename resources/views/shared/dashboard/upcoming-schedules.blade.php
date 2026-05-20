{{--
    Slot for M3 Schedule Suite (owned by Friend 2).
    Replace this empty-state block with real schedule feed when M3 ships.
    Contract:
      - $upcomingSchedules (Collection) — when feature is ready, controller should pass it
      - Fields expected: course_code, activity_label, start_at (Carbon), location_label
--}}
<div class="card" data-testid="dashboard-upcoming-schedules">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--fg-3);">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <div class="card-ttl">ตารางสอนที่กำลังจะมาถึง</div>
        </div>
    </div>
    <div style="padding: 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 14px;">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5"
             style="color: var(--fg-3); opacity: 0.6; flex-shrink: 0;">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <div>
            <div style="font-size: 13px; font-weight: 600; color: var(--fg-2); margin-bottom: 2px;">ยังไม่มีตารางสอน</div>
            <div style="font-size: 11.5px; color: var(--fg-3);">กิจกรรมจะปรากฏที่นี่เมื่อระบบจัดตาราง (M3) พร้อมใช้งาน</div>
        </div>
    </div>
</div>
