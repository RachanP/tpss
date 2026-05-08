// Lecturer view — read-only personal schedule, day list
function LecturerView() {
  const { DAYS, TYPES, ACTIVITIES } = window.TPSS_DATA;
  // Filter to activities where this instructor appears
  const me = 'รศ.ดร.มาลี ชาญวิชา';
  const mine = ACTIVITIES.filter(a => a.ins.includes(me));

  const byDay = DAYS.map((d, i) => ({
    ...d,
    items: mine.filter(a => a.day === i),
  }));

  const totalHours = mine.reduce((sum, a) => {
    const [sh, sm] = a.s.split(':').map(Number);
    const [eh, em] = a.e.split(':').map(Number);
    return sum + (eh - sh) + (em - sm) / 60;
  }, 0);

  return (
    <div className="page">
      <div className="stats-strip">
        <StatBlock label="รวมสัปดาห์นี้" value={totalHours.toFixed(1)} unit=" ชม." tone="hi"
                   sub={`${mine.length} กิจกรรม`}/>
        <StatBlock label="ภาระงานเทียบเกณฑ์" value="92" unit="%"
                   sub="เกณฑ์ 30 ชม./สัปดาห์"/>
        <StatBlock label="กลุ่มที่ดูแล" value="6" sub="A1–A6"/>
        <StatBlock label="หอผู้ป่วย" value="1" sub="Ward 7B · ร.พ.ศิริราช"/>
      </div>

      {byDay.map(d => d.items.length > 0 && (
        <section className="day-block" key={d.nm}>
          <div className="day-hd">
            <div className="day-nm">{d.nm}</div>
            <div className="day-dt">{d.dt}</div>
            <div className="day-count">{d.items.length} กิจกรรม</div>
          </div>
          <div className="alert-list">
            {d.items.map((a, i) => {
              const ty = TYPES[a.type] || TYPES.lecture;
              return (
                <div className="act-row" key={i} style={{ '--c': `var(${ty.v})` }}>
                  <div className="act-time">
                    <div className="act-time-s">{a.s}</div>
                    <div className="act-time-e">{a.e}</div>
                  </div>
                  <div className="act-bar"/>
                  <div className="act-body">
                    <span className={'tag tag-' + a.type}>{ty.lbl}</span>
                    <div className="act-nm">{a.nm}</div>
                    <div className="act-loc">{a.loc}</div>
                  </div>
                  <div className="act-grps">
                    {a.grps.map(g => <span className="grp" key={g}>{g}</span>)}
                  </div>
                </div>
              );
            })}
          </div>
        </section>
      ))}
    </div>
  );
}
window.LecturerView = LecturerView;
