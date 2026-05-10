// Top-level app — owns role + route, renders shell
const USERS = {
  maker:    { role: 'maker',    name: 'รศ.ดร.มาลี ชาญวิชา', title: 'หัวหน้าวิชา',
              context: ['สิทธิ์: จัดทำและส่งตารางสอน', 'วิชา: NURS 3002 · การพยาบาลอนามัยชุมชน'] },
  approver: { role: 'approver', name: 'ศ.ดร.พรทิพย์ อุ่นใจ', title: 'รองคณบดีฝ่ายวิชาการ' },
  lecturer: { role: 'lecturer', name: 'รศ.ดร.มาลี ชาญวิชา', title: 'อาจารย์ผู้สอน' },
  admin:    { role: 'admin',    name: 'System Administrator', title: 'ผู้ดูแลระบบ' },
  staff:    { role: 'staff',    name: 'นางสาวสุดา ใจมั่น',     title: 'เจ้าหน้าที่' },
};

const ROUTE_TITLES = {
  dashboard: 'ภาพรวม',
  schedule:  'ตารางสอน · สัปดาห์ที่ 3',
  courses:   'วิชาที่รับผิดชอบ',
  submission:'สถานะการส่ง',
  queue:     'คิวอนุมัติตารางสอน',
  all:       'ตารางทั้งหมด',
  reports:   'รายงาน',
  mine:      'ตารางสอนของฉัน',
  workload:  'ภาระงานสอน',
};

function App() {
  const [user, setUser]   = useState(null);
  const [route, setRoute] = useState('dashboard');

  function login(roleKey) {
    setUser(USERS[roleKey]);
    if (roleKey === 'maker')    setRoute('dashboard');
    if (roleKey === 'approver') setRoute('queue');
    if (roleKey === 'lecturer') setRoute('mine');
    if (roleKey === 'staff')    setRoute('dashboard');
    if (roleKey === 'admin')    setRoute('dashboard');
  }
  function logout() { setUser(null); }

  if (!user) return <Login onLogin={login}/>;

  let body, week, view, actions;
  if (user.role === 'maker' && route === 'dashboard') {
    body = <MakerDashboard onGotoSchedule={() => setRoute('schedule')}/>;
    actions = <Button kind="primary" icon="send">ส่งอนุมัติ</Button>;
  } else if (user.role === 'maker' && route === 'schedule') {
    body = <ScheduleGrid/>;
    week = { label: '5 – 9 พ.ค. 2569', n: 3 };
    view = { value: 'grid', options: [{ key: 'list', label: 'รายการ' }, { key: 'grid', label: 'ตาราง' }] };
    actions = <>
      <Button kind="secondary" icon="bars">สรุปภาระงาน</Button>
      <Button kind="secondary" icon="plus">เพิ่มกิจกรรม</Button>
      <Button kind="primary"   icon="send">ส่งอนุมัติ</Button>
    </>;
  } else if (user.role === 'approver') {
    body = <ApproverQueue/>;
  } else if (user.role === 'lecturer') {
    body = <LecturerView/>;
    week = { label: '5 – 9 พ.ค. 2569', n: 3 };
  } else {
    body = (
      <div className="page" style={{padding:'40px 28px'}}>
        <div className="empty">
          <Icon name="calendar" size={32} stroke={1.5}/>
          <h3>หน้านี้ยังไม่ได้สาธิตในชุด UI Kit</h3>
          <p>โปรดเลือก ตารางสอน, คิวอนุมัติ, หรือ ตารางของฉัน เพื่อดูตัวอย่าง</p>
          <Button kind="secondary" onClick={() => setRoute({maker:'dashboard',approver:'queue',lecturer:'mine'}[user.role] || 'dashboard')}>
            กลับหน้าหลัก
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="app-shell">
      <Sidebar user={user} route={route} onNav={setRoute} onLogout={logout}/>
      <div className="main">
        <Topbar
          title={ROUTE_TITLES[route] || ''}
          week={week}
          view={view}
          actions={actions}
        />
        {body}
      </div>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
