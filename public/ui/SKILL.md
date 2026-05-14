---
name: tpss-design
description: Use this skill to generate well-branded interfaces and assets for TPSS (Teaching & Practicum Scheduling System, Faculty of Nursing, Mahidol University), either for production or throwaway prototypes/mocks. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files (`colors_and_type.css`, `assets/`, `preview/`, `ui_kits/tpss/`).

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.

If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.

Key constraints to enforce automatically when designing for TPSS:
- **Single navy theme** for all roles. No per-role hue accents — they clash with semantic statuses (red=Conflict, green=Approved). Differentiate roles via the neutral monospaced text badge near the user name.
- **Enterprise density.** Sharp 2–4 px corners, hairline 1 px gray borders, no drop shadows on data surfaces. Think Workday / Canvas / hospital admin tools, not playful startup app.
- **Thai-first copy.** Sarabun for UI; JetBrains Mono for codes/IDs/times. Buddhist year on dates. Outlined Lucide-style SVG icons; emoji only in approver course lists.
- **Color is signal, not decoration.** Saturated tones appear only as semantic status. Canvas stays white/off-white; navy is reserved for chrome, active states, and primary actions.
