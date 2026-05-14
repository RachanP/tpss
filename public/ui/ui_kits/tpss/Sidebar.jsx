// Sidebar — dark navy chrome shared by all logged-in views.
function Sidebar({ user, route, onNav, onLogout }) {
  const items = {
    maker: [
      { key: 'dashboard', icon: 'grid',     label: 'ภาพรวม' },
      { key: 'schedule',  icon: 'calendar', label: 'ตารางสอน',     badge: '2', badgeKind: 'red' },
      { key: 'courses',   icon: 'book',     label: 'วิชาที่รับผิดชอบ', badge: '2', badgeKind: 'soft' },
      { key: 'submission',icon: 'send',     label: 'สถานะการส่ง' },
    ],
    approver: [
      { key: 'queue',     icon: 'send',     label: 'คิวอนุมัติ',  badge: '3', badgeKind: 'red' },
      { key: 'all',       icon: 'grid',     label: 'ตารางทั้งหมด' },
      { key: 'reports',   icon: 'bars',     label: 'รายงาน' },
    ],
    lecturer: [
      { key: 'mine',      icon: 'calendar', label: 'ตารางของฉัน' },
      { key: 'workload',  icon: 'bars',     label: 'ภาระงานสอน' },
    ],
  }[user.role] || [];

  return (
    <aside className="sidebar">
      <div className="sb-logo">
        <div className="sb-mark">TPSS</div>
        <div>
          <div className="sb-name">TPSS</div>
          <div className="sb-sub">ระบบจัดตารางสอน</div>
        </div>
      </div>

      <div className="sb-user">
        <Avatar name={user.name} size={34} />
        <div style={{ minWidth: 0 }}>
          <div className="sb-uname">{user.name}</div>
          <div className="sb-urole">
            <RoleBadge role={user.role} />
            <span className="sb-urole-text">{user.title}</span>
          </div>
        </div>
      </div>

      {user.context && (
        <div className="sb-badge">
          {user.context.map((line, i) => <div key={i}>{line}</div>)}
        </div>
      )}

      <nav className="sb-nav">
        <div className="sb-sec">เมนูหลัก</div>
        {items.map(it => (
          <button key={it.key}
                  className={'nv' + (route === it.key ? ' on' : '')}
                  onClick={() => onNav(it.key)}>
            <Icon name={it.icon} size={15} stroke={2} />
            <span>{it.label}</span>
            {it.badge && (
              <span className={'nv-bd nv-bd-' + it.badgeKind}>{it.badge}</span>
            )}
          </button>
        ))}
      </nav>

      <div className="sb-foot">
        <button className="nv" onClick={onLogout}>
          <Icon name="logout" size={15} stroke={2} />
          <span>ออกจากระบบ</span>
        </button>
      </div>
    </aside>
  );
}
window.Sidebar = Sidebar;
