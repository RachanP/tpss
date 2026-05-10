# TPSS UI Kit

High-fidelity recreation of the TPSS scheduling app for the Faculty of Nursing, Mahidol University. This is the **canonical visual reference** — pixel-true to the existing HTML mocks at `ระบบจัดตาราง/mock/`.

## Open

[`index.html`](./index.html) — interactive click-thru of 4 core screens:

1. **Login** — role selection (5 roles → unified navy theme, role pill on profile)
2. **Maker · Dashboard** — หัวหน้าวิชา's overview with conflict cards & week stats
3. **Maker · Schedule editor** — week grid with activity blocks & conflict highlighting
4. **Approver · Submission queue** — pending courses awaiting executive approval
5. **Lecturer · My schedule** — read-only view for individual instructors

## Files

| File | Role |
|---|---|
| `index.html` | Shell — boots React + Babel, mounts `<App/>`, includes the 5 screens |
| `App.jsx` | Top-level router (in-memory) + role state |
| `Sidebar.jsx` | Dark navy left rail (logo, user profile + neutral role pill, nav, logout) |
| `Topbar.jsx` | Sticky top bar — page title, week navigator, view toggle, action buttons |
| `Login.jsx` | Two-pane login w/ role list |
| `MakerDashboard.jsx` | Stats strip, week summary, conflict alerts |
| `ScheduleGrid.jsx` | Week × time grid with activity blocks (renders DATA) |
| `ApproverQueue.jsx` | Submission cards with status pills & approve/reject actions |
| `LecturerView.jsx` | Per-day list of an instructor's own activities |
| `primitives.jsx` | `<Button>`, `<Pill>`, `<Tag>`, `<StatBlock>`, `<Avatar>`, `<Icon>` |
| `data.js` | Sample courses, weeks, activities, conflicts, instructors |

## What's faithful, what's not

✅ Faithful: layout, color, type, sidebar chrome, week-grid block style, stat strip, status pill semantics, conflict-card presentation, role-as-text-badge.

❌ Cut for brevity: full 6-week navigator, drag-to-create activity, the conflict-detail modal, real LMS integration, full data CRUD. These are stubbed visually but not interactive.
