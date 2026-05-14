// Topbar — sticky page chrome with title, week navigator, view toggle, actions
function Topbar({ title, week, view, onViewChange, actions }) {
  return (
    <header className="topbar">
      <div className="tb-title">{title}</div>
      <div className="tb-sep" />

      {week && (
        <div className="wk-nav">
          <button className="wk-btn">‹</button>
          <span className="wk-lbl">{week.label}<span className="wk-pill">สัปดาห์ {week.n}</span></span>
          <button className="wk-btn">›</button>
        </div>
      )}

      {view && (
        <div className="view-toggle">
          {view.options.map(o => (
            <button key={o.key}
                    className={'vt-btn' + (view.value === o.key ? ' on' : '')}
                    onClick={() => onViewChange?.(o.key)}>{o.label}</button>
          ))}
        </div>
      )}

      <div className="tb-right">{actions}</div>
    </header>
  );
}
window.Topbar = Topbar;
