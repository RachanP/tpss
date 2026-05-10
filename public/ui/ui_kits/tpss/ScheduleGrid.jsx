// Schedule grid — week × time blocks with activity cards
function ScheduleGrid() {
  const { DAYS, TYPES, ACTIVITIES } = window.TPSS_DATA;
  const TIMES = ['07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00'];

  return (
    <div className="page">
      <div className="sch-grid">
        <div className="sch-row sch-hdr">
          <div className="sch-cell sch-time"></div>
          {DAYS.map(d => (
            <div className="sch-cell sch-hd" key={d.nm}>
              <div className="sch-hd-nm">{d.nm}</div>
              <div className="sch-hd-dt">{d.dt}</div>
            </div>
          ))}
        </div>
        {TIMES.map(t => {
          const hh = t.split(':')[0];
          return (
            <div className="sch-row" key={t}>
              <div className="sch-cell sch-time">{t}</div>
              {DAYS.map((d, di) => {
                const acts = ACTIVITIES.filter(a => a.day === di && a.s.startsWith(hh));
                return (
                  <div className="sch-cell sch-slot" key={di}>
                    {acts.map((a, i) => {
                      const ty = TYPES[a.type] || TYPES.lecture;
                      return (
                        <div key={i}
                             className={'sch-act' + (a.conflict ? ' conflict' : '')}
                             style={{ '--c': `var(${ty.v})` }}>
                          <div className="sch-act-hd">
                            <span className={'tag tag-' + a.type}>{ty.lbl}</span>
                            <span className="sch-act-time">{a.s}–{a.e}</span>
                          </div>
                          <div className="sch-act-nm">{a.nm}</div>
                          <div className="sch-act-loc">{a.loc}</div>
                          <div className="sch-act-meta">
                            {a.grps.map(g => <span className="grp" key={g}>{g}</span>)}
                            {a.conflict && (
                              <span className="conflict-flag">
                                <Icon name="alert" size={11} stroke={2.2}/>ตารางชน
                              </span>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                );
              })}
            </div>
          );
        })}
      </div>
    </div>
  );
}
window.ScheduleGrid = ScheduleGrid;
