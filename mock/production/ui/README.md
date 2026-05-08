# TPSS Design System

> Teaching & Practicum Scheduling System — Faculty of Nursing, Mahidol University

A complex academic and clinical scheduling web application. Built for five distinct user roles (System Admin, Maker / หัวหน้าวิชา, Approver / ผู้บริหาร, Staff / เจ้าหน้าที่, Lecturer / อาจารย์ผู้สอน) with smart conflict detection, workload management, and executive approval workflows.

The product is **Thai-first**, **data-dense**, and lives in a professional medical/academic context. The visual language is calm, formal navy with restrained gold; it must reduce eye strain across long sessions of grid editing.

---

## Sources

- **Codebase:** `ระบบจัดตาราง/` (mounted, read-only)
  - `mock/{login,admin,maker,approver,staff,lecturer}.html` — full-page HTML mocks per role
  - `flowchart/{overview,roles}.pdf` — process diagrams
  - `migration/database_v1.sql`, `migration/ER_v1.jpg` — data model
  - `Doc/` — academic scheduling specs (Thai)
  - `CLAUDE.md` — repo-level instructions

No Figma; no slide deck. The mocks are the canonical source for visual style.

---

## Index

| File / folder | Purpose |
|---|---|
| `colors_and_type.css` | All design tokens — colors, type scale, radii, shadows, spacing, motion |
| `assets/` | Logo mark, lockup, role icons (referenced from CDN where unavailable) |
| `fonts/` | Webfonts (currently `13Fonts.zip` — Sarabun is the primary; see Type) |
| `preview/` | Per-card design-system specimens (registered for the Design System tab) |
| `ui_kits/tpss/` | Hi-fi recreation of core TPSS screens (Maker schedule editor, Approver queue, Lecturer view) |
| `SKILL.md` | Agent skill manifest — invoke this skill when designing for TPSS |

---

## Brand at a glance

- **Primary:** `#002454` Mahidol Navy — chrome, headers, primary buttons
- **Accent:** `#C9A862` Soft gold — used very sparingly: logo edge, focused-row marker, premium highlights only. Never as a CTA.
- **Surface:** Near-white `oklch(99% 0.004 230)` with a faint cool cast; data tables sit on pure white.
- **Type:** **Sarabun** (Thai/Latin) for UI; **JetBrains Mono** for codes, IDs, time tokens.
- **Status palette (semantic, never decorative):**
  - 🔴 Conflict / Error — `oklch(52% .20 25)` red
  - 🟠 Warning / Workload — `oklch(62% .16 65)` amber
  - 🟢 Approved / Success — `oklch(52% .15 148)` green
  - ⚪ Draft / Pending — neutral gray
- **Role accents** (used for the active user only — sidebar avatar ring, active nav, user-scoped badges):
  Admin = violet · Maker = indigo · Approver = green · Staff = teal · Lecturer = red

---

## Content fundamentals

**Language.** Thai is primary (UI labels, buttons, data, errors). English is secondary, used for technical labels (`Conflict`, `Workload`, `SDL`, course codes like `NURS 3002`), section eyebrows, and status names where the team-of-record uses English (`Lecture`, `Lab`, `Ward`, `Conference`, `Exam`).

**Tone.** Highly professional, efficient, formal-but-warm. The product addresses faculty members; it uses respectful Thai conventions (titles always written out — `รศ.ดร.วิลาวรรณ สมบูรณ์`, `อ.ณภัทร ปั้นทอง`). Avoid casual particles (ครับ/ค่ะ/นะ); avoid exclamation marks except in error toasts.

**Person.** The system addresses the user implicitly — buttons are imperative verbs (`บันทึกตาราง`, `ส่งขออนุมัติ`, `อนุมัติ`, `ตีกลับ`). No "you/I"; no "ของคุณ"/"ของฉัน" unless disambiguating ownership ("ตารางสอนของฉัน" on Lecturer's page).

**Casing.** Latin words follow normal sentence case in Thai sentences (`ตรวจ Conflict อัตโนมัติ`, not `ตรวจ CONFLICT`). UPPERCASE is reserved for tags (`LECTURE`, `LAB`, `WARD`) and section eyebrows tracked at +.1em.

**Numbers.** Tabular, comma-separated, with explicit Thai units (`42 ชม.`, `120 คน`, `9 กลุ่ม`). Buddhist year on dates (`2569`, not 2026); 24-hour times (`08:00–10:00`, en-dash).

**Emoji.** Sparingly, only as supportive accents in approver/admin lists (`📚 🤱 🧠 👴`) where they help scan a long course list. **Never** in primary UI chrome, buttons, or status badges. Status uses outlined icons, not emoji.

**Examples (real product copy):**
- Eyebrow: `คณะพยาบาลศาสตร์ • มหาวิทยาลัยมหิดล`
- Hero title: `ระบบจัดตารางสอนและฝึกปฏิบัติ`
- Hero desc: `บริหารตารางสอนและฝึกปฏิบัติพยาบาลศาสตร์ รองรับหลายกลุ่ม หลายกิจกรรม หลายสถานที่ฝึก พร้อมระบบตรวจสอบตารางชนอัตโนมัติ`
- Conflict: `ตารางชนระหว่างอาจารย์` / `อ.วิลาวรรณ มีกิจกรรมซ้อนทับ — Lecture (พยอย 384) เวลา 08:00 ทับ Ward เวลา 08:30`
- Warning: `ภาระงาน เกินเกณฑ์ · 38 ชม./สัปดาห์ (เกินเกณฑ์ 30 ชม.) · บันทึกได้แต่ควรกระจายงานก่อนส่งอนุมัติ`

---

## Visual foundations

**Colors.** Navy carries the brand into every page via the sidebar; the canvas is otherwise near-white. Color is a *signal* — saturated tones (red/amber/green) appear only as status. Decorative gradients are forbidden. Acceptable surface ramp: `--bg` (canvas) → `--surface` (cards) → `--bg-2` (table headers, disabled fields).

**Type.** Sarabun 400/500/600/700 for UI; weights step in 100s — never use 800 except for dashboard KPI numerals (22 px / 800 / tabular). Body 13 px, labels 11–12 px, eyebrows 9.5 px UPPERCASE +.1em. Line-height 1.45 for Thai (descenders/marks need room). JetBrains Mono for any token / ID / time.

**Spacing.** 4-pt grid: `4 · 6 · 8 · 12 · 16 · 24 · 32`. Card padding 14–16; row gap 6 in lists, 12 in form grids. Side rail is fixed at 220–240 px.

**Backgrounds.** Flat. No images, no patterns, no gradients except: a single subtle gold-→-navy line on the login left panel, and brand-navy → `#16498f` 135° on the sidebar logo tile (lifted from the original mocks). Imagery, when added, should be photographic, cool/desaturated, never warm.

**Borders & corners.** Hairline 1 px `--border` (oklch 92% 0.01 230) for separators; 1.5 px for inputs. Radii: `xs 4 / sm 6 / md 8 / lg 12 / xl 16 / pill 999`. Buttons & inputs `md`; cards `lg`; status pills `pill`. Approachable but formal — no over-rounded "playful" 20+ px on functional elements.

**Cards.** White, `lg` radius, **1 px hairline border** + a single soft shadow (`--shadow-sm`: 0 1 2 oklch(0% 0 0 / .04)). Never both a heavy shadow AND a colored border. The conflict / warning / success alert variant adds a 4 px left accent.

**Shadows.** Three levels only:
- `--shadow-sm` — resting cards, inputs
- `--shadow-md` — popovers, dropdowns
- `--shadow-lg` — modals, the conflict-detail dialog

**Motion.** Minimal. `--dur-base: 120 ms`, `--ease-out: cubic-bezier(.2, .6, .2, 1)`. Use for hover background, focus ring, badge entry. Animate width/opacity, never layout. No bounces, no spring overshoot — this is a clinical-academic tool.

**Hover.** Background only — `oklch(97% .004 240)` for rows, `color-mix(brand-navy 6%, white)` for primary surfaces. Never opacity 0.8 on whole cards. Active rows get a left-edge `var(--brand-navy)` 3-px marker.

**Press.** No transform / shrink. Press = darken background 4–6%, no scale. (Scheduling is a precise activity; users misclick when buttons jump.)

**Focus.** 3 px ring `color-mix(brand-navy 18%, transparent)` outside the border, never inside. Always visible — a11y is non-negotiable for faculty users.

**Transparency / blur.** Avoid except inside inline tags over colored cells (`rgba(255,255,255,.7)` chips inside activity blocks).

**Layout rules.**
- Fixed left sidebar 220–240 px (dark navy chrome).
- Fixed top bar 56 px on each page; sticky.
- Main grid: max-width none; tables expand to fill. Min usable width 1200 px.
- Modal: 480 px form / 720 px conflict detail.

---

## Iconography

The product uses **Feather-style outlined SVG icons** drawn inline (1.5–2 px stroke, round caps, round joins, 24 × 24 viewBox, sized 13–18 px in use). They are written directly in markup throughout `mock/*.html` — there is **no icon font, no sprite**, and no external library reference.

When designing for TPSS:
1. **Prefer Lucide** (`https://unpkg.com/lucide-static@latest`) — it's the closest match to the existing inline icons (same Feather DNA). This is a flagged substitution for production agents: a unified Lucide bundle covers the same set the mocks draw inline.
2. **Stroke spec:** 1.75–2 px, `round` linecaps and joins, no fills. At 13 px or smaller, bump stroke to 2 px to keep marks legible.
3. **Color:** inherit `currentColor`. In sidebar nav at 80% opacity; in pills, exactly the pill's text color; in primary buttons, `#fff`.

Status icons in semantic pills are **outlined**, not emoji:
- Conflict → exclamation circle
- Warning → triangle-exclamation
- Success → check-circle
- Draft → simple `dot` (6 px circle), no glyph
- Info → info-circle

**Emoji** is permitted **only** in approver/admin queue lists where each course is decorated with a topical emoji (`📚 🤱 🧠 👴 🎯 👶 🏘️`) — preserved from the existing product. Never in chrome, never in status, never in buttons.

**Unicode marks** used as icons in legacy spots (`✏ 🗑 ⚠ ✓ ! ○`) should be **upgraded to outlined SVG** in any new work — the mocks use them as a shortcut, not as the design.

**Logos** live in `assets/`:
- `logo-mark.svg` — calendar mark only, 24 × 24
- `logo-lockup.svg` — mark + "TPSS" wordmark + tagline, 220 × 56

The mark sits inside a navy 32 × 32 tile with a 1 px gold border — that combination is the only place gold appears on chrome.

---

## Substitutions to flag

- **Sarabun** is loaded from Google Fonts (`https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700`). The repo includes `fonts/13Fonts.zip` but it has not been unpacked into `fonts/`. **Action:** unzip the bundle and confirm whether the `.ttf` files are licensed for embedding; otherwise Google Fonts is the source of truth.
- **Iconography:** the mocks hand-roll inline SVG. For consistency in new work we standardize on **Lucide** (CDN). Any stroke or shape that disagrees with Lucide should be added to `assets/icons/` as a one-off.
- **No brand mark file** was provided — `assets/logo-mark.svg` and `assets/logo-lockup.svg` were authored from the inline SVG in `mock/login.html`. If the Faculty has an official Mahidol Nursing logo, swap it in.

---

## See also

- [`colors_and_type.css`](./colors_and_type.css) — copy/paste design tokens
- [`SKILL.md`](./SKILL.md) — invoke this skill in Claude Code
- [`ui_kits/tpss/`](./ui_kits/tpss/) — high-fidelity reference screens
