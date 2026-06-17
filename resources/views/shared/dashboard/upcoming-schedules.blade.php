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
            <span class="dash-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <div class="card-ttl" role="heading" aria-level="2">ตารางสอนถัดไป</div>
        </div>
    </div>
    <div class="upcoming-schedules-empty">
        <div class="upcoming-schedules-empty-copy">
            <span class="dash-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <div style="font-size: 13px; font-weight: 600; color: var(--fg-2); margin-bottom: 2px;">ยังไม่มีตารางสอน</div>
            <div style="font-size: 11.5px; color: var(--fg-3);">เมื่อมีตารางสอนที่ใกล้ถึง ระบบจะแสดงให้ติดตามจากส่วนนี้</div>
        </div>
    </div>
</div>

<style>
    [data-testid="dashboard-upcoming-schedules"] {
        overflow: hidden;
    }

    [data-testid="dashboard-upcoming-schedules"] .card-hdr {
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 9%, var(--surface)), transparent 72%),
            color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
    }

    [data-testid="dashboard-upcoming-schedules"] .card-hdr > div {
        min-width: 0;
        flex-wrap: wrap;
    }

    .upcoming-schedules-empty {
        min-height: 220px;
        padding: 24px;
        border-top: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        background:
            radial-gradient(circle at 50% 22%, color-mix(in oklch, var(--brand-navy) 7%, transparent), transparent 35%),
            color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .upcoming-schedules-empty-copy {
        max-width: 360px;
        line-height: 1.55;
    }

    @media (max-width: 540px) {
        .upcoming-schedules-empty {
            min-height: 150px;
            padding: 16px !important;
        }
    }
</style>
