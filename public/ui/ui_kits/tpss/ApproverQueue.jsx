// Approver queue — submissions awaiting executive approval
function ApproverQueue() {
  const { SUBMISSIONS } = window.TPSS_DATA;
  const [filter, setFilter] = useState('pending');

  const filtered = SUBMISSIONS.filter(s => filter === 'all' ? true : s.status === filter);

  const tones = { pending: 'warning', approved: 'success', rejected: 'conflict' };
  const labels = { pending: 'รออนุมัติ', approved: 'อนุมัติแล้ว', rejected: 'ตีกลับ' };
  const icons  = { pending: 'tri', approved: 'checkc', rejected: 'x' };

  return (
    <div className="page">
      <div className="filter-bar">
        {[
          { k: 'pending',  n: 'รออนุมัติ',  c: SUBMISSIONS.filter(s=>s.status==='pending').length },
          { k: 'approved', n: 'อนุมัติแล้ว', c: SUBMISSIONS.filter(s=>s.status==='approved').length },
          { k: 'rejected', n: 'ตีกลับ',     c: SUBMISSIONS.filter(s=>s.status==='rejected').length },
          { k: 'all',      n: 'ทั้งหมด',    c: SUBMISSIONS.length },
        ].map(f => (
          <button key={f.k}
                  className={'filter-tab' + (filter === f.k ? ' on' : '')}
                  onClick={() => setFilter(f.k)}>
            {f.n}<span className="filter-count">{f.c}</span>
          </button>
        ))}
        <div style={{flex:1}}/>
        <div className="search">
          <Icon name="search" size={13} stroke={2}/>
          <input className="input" placeholder="ค้นหารหัสวิชา หรือชื่อหัวหน้าวิชา..."/>
        </div>
      </div>

      <div className="sub-list">
        {filtered.map(s => (
          <div className="sub-row" key={s.id}>
            <div className="sub-code">{s.code}</div>
            <div className="sub-main">
              <div className="sub-nm">{s.course}</div>
              <div className="sub-meta">
                <span>โดย <strong>{s.by}</strong></span>
                <span className="dot-sep">·</span>
                <span>ส่งเมื่อ {s.at}</span>
              </div>
            </div>
            <div className="sub-stats">
              <div className="sub-stat"><span className="n">{s.acts}</span><span className="u">กิจกรรม</span></div>
              <div className="sub-stat"><span className="n">{s.grps}</span><span className="u">กลุ่ม</span></div>
              <div className="sub-stat"><span className="n">{s.wks}</span><span className="u">สัปดาห์</span></div>
              {s.conflicts > 0 && (
                <div className="sub-stat err">
                  <span className="n">{s.conflicts}</span><span className="u">conflict</span>
                </div>
              )}
            </div>
            <Pill tone={tones[s.status]} icon={icons[s.status]}>{labels[s.status]}</Pill>
            <div className="sub-actions">
              {s.status === 'pending' ? (
                <>
                  <Button kind="success" size="sm" icon="check">อนุมัติ</Button>
                  <Button kind="danger"  size="sm" icon="x">ตีกลับ</Button>
                </>
              ) : (
                <Button kind="ghost" size="sm">ดูรายละเอียด</Button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
window.ApproverQueue = ApproverQueue;
