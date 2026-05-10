// Login — single unified screen. Email determines role downstream.
function Login({ onLogin }) {
  const [email, setEmail] = useState('');
  const [pwd, setPwd]     = useState('');

  // Demo: route by email prefix → role. Real system maps via directory.
  function detectRole(em) {
    const e = (em || '').toLowerCase();
    if (e.startsWith('admin'))    return 'admin';
    if (e.startsWith('dean'))     return 'approver';
    if (e.startsWith('staff') || e.startsWith('office')) return 'staff';
    if (e.startsWith('malee') || e.startsWith('wilawan') || e.startsWith('head')) return 'maker';
    return 'lecturer';
  }

  function submit(e) {
    e && e.preventDefault();
    if (!email) return;
    onLogin(detectRole(email));
  }

  return (
    <div className="login-shell">

      {/* LEFT — institutional brand panel */}
      <div className="login-left">
        <div>
          <div className="seal-row">
            <div className="seal">
              <svg viewBox="0 0 80 80" width="56" height="56" fill="none">
                {/* Stylised Mahidol-style seal placeholder — gold ring + monogram */}
                <circle cx="40" cy="40" r="38" stroke="var(--brand-gold)" strokeWidth="1.5" />
                <circle cx="40" cy="40" r="32" stroke="var(--brand-gold)" strokeWidth="0.8" />
                <text x="40" y="46" textAnchor="middle"
                      fontFamily="serif" fontSize="22" fontWeight="700"
                      fill="var(--brand-gold)" letterSpacing="1">MU</text>
                <path d="M22 58 Q40 64 58 58" stroke="var(--brand-gold)" strokeWidth="0.8" fill="none"/>
              </svg>
            </div>
            <div className="seal-text">
              <div className="seal-th">มหาวิทยาลัยมหิดล</div>
              <div className="seal-en">MAHIDOL UNIVERSITY</div>
              <div className="seal-faculty">คณะพยาบาลศาสตร์ · Faculty of Nursing</div>
            </div>
          </div>

          <div className="brand-rule"></div>

          <p className="eyebrow">Teaching &amp; Practicum Scheduling System</p>
          <h1 className="hero-title">ระบบจัดตารางสอน<br/>และฝึกปฏิบัติ</h1>
          <p className="hero-desc">
            บริหารตารางสอนและฝึกปฏิบัติพยาบาลศาสตร์
            รองรับหลายกลุ่ม หลายกิจกรรม หลายสถานที่ฝึก
            พร้อมระบบตรวจสอบตารางชนอัตโนมัติและสายอนุมัติของคณะ
          </p>
        </div>

        <div className="login-foot-wrap">
          <p className="login-foot">TPSS · ปีการศึกษา 2569 · v1.0</p>
        </div>
      </div>

      {/* RIGHT — single auth form */}
      <div className="login-right">
        <div className="login-inner">
          <p className="form-eyebrow">เข้าสู่ระบบ · Sign in</p>
          <h2 className="section-heading">ลงชื่อเข้าใช้งาน</h2>
          <p className="section-sub">
            ใช้บัญชี <code>@nurse.mahidol.ac.th</code> ของคุณ ระบบจะกำหนดสิทธิ์ตามบัญชีโดยอัตโนมัติ
          </p>

          <form onSubmit={submit}>
            <div className="field">
              <label className="flbl">อีเมลมหาวิทยาลัย</label>
              <input className="input" type="email" autoFocus
                     placeholder="name@nurse.mahidol.ac.th"
                     value={email} onChange={e => setEmail(e.target.value)} />
            </div>
            <div className="field">
              <label className="flbl">รหัสผ่าน</label>
              <input className="input" type="password"
                     placeholder="••••••••••"
                     value={pwd} onChange={e => setPwd(e.target.value)} />
            </div>

            <div className="login-row">
              <label className="check">
                <input type="checkbox" defaultChecked />
                <span>จดจำการเข้าสู่ระบบบนอุปกรณ์นี้</span>
              </label>
              <a href="#" className="forgot">ลืมรหัสผ่าน?</a>
            </div>

            <button type="submit" className="btn btn-primary btn-block">
              เข้าสู่ระบบ
            </button>
          </form>

          <div className="demo-hint">
            <div className="demo-lbl">บัญชีตัวอย่างสำหรับทดลองระบบ</div>
            <div className="demo-list">
              <button type="button" className="demo-row" onClick={() => { setEmail('malee.cha@nurse.mahidol.ac.th'); setPwd('demo'); }}>
                <code>malee.cha@…</code><span>หัวหน้าวิชา (Maker)</span>
              </button>
              <button type="button" className="demo-row" onClick={() => { setEmail('dean@nurse.mahidol.ac.th'); setPwd('demo'); }}>
                <code>dean@…</code><span>รองคณบดี (Approver)</span>
              </button>
              <button type="button" className="demo-row" onClick={() => { setEmail('napat.pun@nurse.mahidol.ac.th'); setPwd('demo'); }}>
                <code>napat.pun@…</code><span>อาจารย์ผู้สอน (Lecturer)</span>
              </button>
            </div>
          </div>

          <p className="login-help">
            หากเข้าใช้งานไม่ได้ โปรดติดต่อ หน่วยเทคโนโลยีสารสนเทศ คณะพยาบาลศาสตร์ มหาวิทยาลัยมหิดล
          </p>
        </div>
      </div>

    </div>
  );
}
window.Login = Login;
