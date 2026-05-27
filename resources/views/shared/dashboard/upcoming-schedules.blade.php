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
            <div class="card-ttl">ตารางสอนถัดไป</div>
        </div>
    </div>
    <div class="upcoming-schedules-empty">
        <div class="upcoming-schedules-empty-copy">
            <div style="font-size: 13px; font-weight: 600; color: var(--fg-2); margin-bottom: 2px;">ยังไม่มีตารางสอน</div>
            <div style="font-size: 11.5px; color: var(--fg-3);">เมื่อมีตารางสอนที่ใกล้ถึง ระบบจะแสดงให้ติดตามจากส่วนนี้</div>
        </div>
    </div>
</div>

<style>
    [data-testid="dashboard-upcoming-schedules"] {
        overflow: hidden;
    }

    [data-testid="dashboard-upcoming-schedules"] .card-hdr > div {
        min-width: 0;
        flex-wrap: wrap;
    }

    .upcoming-schedules-empty {
        min-height: 220px;
        padding: 24px;
        border-top: 1px solid var(--border);
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
