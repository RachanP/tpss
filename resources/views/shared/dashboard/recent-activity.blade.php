{{--
    Slot for Audit Log feature (owned by Friend 1).
    Replace this empty-state block with real activity feed when audit log ships.
    Contract:
      - $activities (Collection) — when feature is ready, controller should pass it
      - Fields expected: actor_name, action, target_label, created_at (Carbon)
--}}
<div class="card" data-testid="dashboard-recent-activity">
    <div class="card-hdr">
        <div style="display: flex; align-items: center; gap: 10px;">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--fg-3);">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <div class="card-ttl">กิจกรรมล่าสุด</div>
        </div>
    </div>
    <div style="padding: 20px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 14px;">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5"
             style="color: var(--fg-3); opacity: 0.6; flex-shrink: 0;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <div>
            <div style="font-size: 13px; font-weight: 600; color: var(--fg-2); margin-bottom: 2px;">ยังไม่มีกิจกรรมในระบบ</div>
            <div style="font-size: 11.5px; color: var(--fg-3);">รายการเปลี่ยนแปลงจะปรากฏที่นี่เมื่อระบบ Audit Log พร้อมใช้งาน</div>
        </div>
    </div>
</div>
