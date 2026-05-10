// Shared primitives for the TPSS UI Kit. All exposed on window for cross-script use.
const { useState } = React;

/* ── Icon ───────────────────────────────────────────────────── */
const ICONS = {
  grid:     <g><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></g>,
  calendar: <g><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></g>,
  book:     <g><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></g>,
  send:     <g><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></g>,
  bars:     <g><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></g>,
  plus:     <g><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></g>,
  check:    <g><path d="M5 13l4 4L19 7"/></g>,
  x:        <g><path d="M18 6L6 18M6 6l12 12"/></g>,
  alert:    <g><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><circle cx="12" cy="16.2" r=".6" fill="currentColor" stroke="none"/></g>,
  tri:      <g><path d="M12 4l9 16H3z"/><path d="M12 11v4"/><circle cx="12" cy="17.5" r=".6" fill="currentColor" stroke="none"/></g>,
  checkc:   <g><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></g>,
  logout:   <g><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></g>,
  chevron:  <g><path d="M9 18l6-6-6-6"/></g>,
  back:     <g><path d="M19 12H5M12 19l-7-7 7-7"/></g>,
  shield:   <g><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></g>,
  edit:     <g><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></g>,
  users:    <g><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></g>,
  book2:    <g><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></g>,
  search:   <g><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></g>,
};
function Icon({ name, size = 16, stroke = 1.75, ...rest }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none"
         stroke="currentColor" strokeWidth={stroke}
         strokeLinecap="round" strokeLinejoin="round" {...rest}>
      {ICONS[name]}
    </svg>
  );
}

/* ── Button ─────────────────────────────────────────────────── */
function Button({ kind = 'secondary', size = 'md', icon, children, onClick, disabled }) {
  const cls = `btn btn-${kind} btn-${size}` + (disabled ? ' is-disabled' : '');
  return (
    <button className={cls} onClick={onClick} disabled={disabled}>
      {icon && <Icon name={icon} size={size === 'sm' ? 12 : 13} stroke={2} />}
      {children}
    </button>
  );
}

/* ── Pill ───────────────────────────────────────────────────── */
function Pill({ tone = 'info', solid = false, icon, dot = false, children }) {
  const cls = `pill p-${tone}` + (solid ? ' solid' : '');
  return (
    <span className={cls}>
      {dot && <span className="pill-dot" />}
      {icon && <Icon name={icon} size={12} stroke={1.75} />}
      {children}
    </span>
  );
}

/* ── Tag (activity type) ────────────────────────────────────── */
function Tag({ type, children }) {
  return <span className={`tag tag-${type}`}>{children}</span>;
}

/* ── Avatar ─────────────────────────────────────────────────── */
function Avatar({ name, size = 30 }) {
  const initial = (name || '?').trim().charAt(0);
  return (
    <div className="av" style={{ width: size, height: size, fontSize: size * 0.42 }}>
      {initial}
    </div>
  );
}

/* ── StatBlock ──────────────────────────────────────────────── */
function StatBlock({ label, value, unit, sub, tone = 'default' }) {
  return (
    <div className={`st st-${tone}`}>
      <div className="st-lbl">{label}</div>
      <div className="st-val">{value}{unit && <span className="st-unit">{unit}</span>}</div>
      {sub && <div className="st-sub">{sub}</div>}
    </div>
  );
}

/* ── Role badge (neutral text pill) ─────────────────────────── */
function RoleBadge({ role }) {
  const map = {
    admin:    'Admin',
    maker:    'Maker',
    approver: 'Approver',
    staff:    'Staff',
    lecturer: 'Lecturer',
  };
  return <span className="role-badge">{map[role] || role}</span>;
}

Object.assign(window, { Icon, Button, Pill, Tag, Avatar, StatBlock, RoleBadge });
