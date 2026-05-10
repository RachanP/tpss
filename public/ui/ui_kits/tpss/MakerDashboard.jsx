// Maker dashboard — stats, conflict alerts, week summary
function MakerDashboard({ onGotoSchedule }) {
  const { CONFLICTS } = window.TPSS_DATA;
  return (
    <div className="page">
      <div className="stats-strip">
        <StatBlock label="กิจกรรมทั้งหมด" value="27" unit=" รายการ" tone="hi"
                   sub={<><span className="dot" style={{background:'var(--brand-navy)'}}/>สัปดาห์ที่ 3 จาก 6</>}/>
        <StatBlock label="กลุ่มย่อย"      value="9" sub="A1–A9 · 120 คน"/>
        <StatBlock label="Workload เกิน"  value="1" tone="warn"
                   sub={<><span className="dot" style={{background:'var(--status-warning)'}}/>อ.วิรัตน์ · 33 ชม.</>}/>
        <StatBlock label="Conflict"        value="2" tone="err"
                   sub={<><span className="dot" style={{background:'var(--status-conflict)'}}/>ต้องแก้ไขก่อนส่งอนุมัติ</>}/>
      </div>

      <section className="panel">
        <div className="panel-hd">
          <div>
            <h3 className="panel-ttl">รายการ Conflict ที่ต้องแก้ไข</h3>
            <p className="panel-sub">ระบบตรวจพบความขัดแย้งในตารางสอนสัปดาห์ที่ 3 — ต้องแก้ไขก่อนส่งอนุมัติ</p>
          </div>
          <Pill tone="conflict" icon="alert">2 รายการ</Pill>
        </div>

        <div className="alert-list">
          {CONFLICTS.map((c, i) => (
            <div className="alert alert-conflict" key={i}>
              <div className="alert-ic"><Icon name="alert" size={16} stroke={2}/></div>
              <div className="alert-body">
                <div className="alert-ttl">{c.title}</div>
                <div className="alert-desc">{c.desc}</div>
                <button className="alert-link" onClick={onGotoSchedule}>
                  → ไปแก้ไขในตารางสอน
                </button>
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="panel">
        <div className="panel-hd">
          <div>
            <h3 className="panel-ttl">วิชาที่รับผิดชอบ</h3>
            <p className="panel-sub">2 วิชา · ภาคการศึกษา 1/2569</p>
          </div>
        </div>
        <div className="course-list">
          <div className="course-row">
            <div className="course-code">NURS 3002</div>
            <div className="course-nm">การพยาบาลอนามัยชุมชน</div>
            <div className="course-meta">9 กลุ่ม · 27 กิจกรรม · 6 สัปดาห์</div>
            <Pill tone="warning" icon="tri">รอแก้ไข Conflict</Pill>
          </div>
          <div className="course-row">
            <div className="course-code">NURS 3000</div>
            <div className="course-nm">ปฐมนิเทศภาคฝึกปฏิบัติ</div>
            <div className="course-meta">9 กลุ่ม · 8 กิจกรรม · 1 สัปดาห์</div>
            <Pill tone="success" icon="checkc">อนุมัติแล้ว</Pill>
          </div>
        </div>
      </section>
    </div>
  );
}
window.MakerDashboard = MakerDashboard;
