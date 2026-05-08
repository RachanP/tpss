---
name: TPSS Design System
description: Professional Institutional scheduling system for Mahidol University
colors:
  primary: "#002454"
  secondary: "#c9a449"
  neutral-bg: "#f8f9fb"
  surface: "#ffffff"
  status-conflict: "#b82a1e"
  status-warning: "#a87600"
  status-success: "#2d7a3d"
  status-info: "#2b6cb0"
typography:
  display:
    fontFamily: "Sarabun, sans-serif"
    fontSize: "44px"
    fontWeight: 700
    lineHeight: 1.25
  body:
    fontFamily: "Sarabun, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.55
rounded:
  sm: "6px"
  md: "8px"
  lg: "10px"
spacing:
  sm: "8px"
  md: "16px"
  lg: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "10px 20px"
  sidebar:
    backgroundColor: "#111b2d"
    textColor: "#9eb3d4"
---

# Design System: TPSS

## 1. Overview

**Creative North Star: "The Mahidol Navy Data Shell"**

The TPSS Design System is built for efficiency, precision, and institutional authority. It serves the Faculty of Nursing at Mahidol University by providing a clear, high-contrast environment for managing complex scheduling data. The aesthetic is "Professional Institutional," prioritizing legibility and functional signaling over decorative trends.

The system explicitly rejects "SaaS consumerism"—avoiding heavy gradients, playful emojis, and aggressive dark modes. Instead, it uses a deep navy "Chrome" (sidebar and navigation) to provide a stable, authoritative frame for a clean, light-mode workspace.

**Key Characteristics:**
- **Institutional Authority**: Built around Mahidol University's brand colors.
- **Data-First Hierarchy**: Dense data is handled with generous leading and clear borders.
- **Semantic Signaling**: High-saturation colors are reserved strictly for status (Conflicts, Warnings).
- **Age-Inclusive UX**: Large typography and explicit labels ensure usability for all personnel.

## 2. Colors

The palette is anchored by Mahidol Navy and balanced by a very soft cyan-blue background to reduce eye strain during long working sessions.

### Primary
- **Mahidol Navy** (#002454): The core brand color, used for navigation, primary actions, and institutional identity.

### Secondary
- **Soft Gold** (#c9a449): Used sparingly for high-level highlights and secondary branding elements.

### Neutral
- **Page Background** (oklch(98% 0.008 220)): A cool, soft tint that provides a clean canvas without the harshness of pure white.
- **Surface** (#ffffff): Used for cards, inputs, and the main data grid to ensure maximum contrast.
- **Primary Text** (oklch(16% 0.02 240)): Near-black navy for high legibility.

### Semantic Status
- **Conflict** (#b82a1e): High-urgency red for booking collisions.
- **Warning** (#a87600): Muted gold for workload imbalances or non-blocking issues.
- **Success** (#2d7a3d): Calm green for approved or published states.

**The Semantic Signal Rule.** Highly saturated colors are forbidden for decorative use. They are signals that must only appear when the system needs to communicate a specific status or error.

## 3. Typography

The system uses **Sarabun** as the primary typeface for its professional tone and excellent legibility in both Thai and English. **IBM Plex Mono** is used for numerical data and badges to ensure alignment in dense grids.

**Character:** Formal and clear, with generous line heights to accommodate Thai script glyphs.

### Hierarchy
- **Display** (Bold, 44px, 1.25): Reserved for major hero statements or empty state headers.
- **Headline** (Bold, 26px, 1.25): Page-level titles.
- **Title** (Bold, 20px, 1.25): Section-level titles and card headers.
- **Body** (Regular, 14px, 1.55): Default reading size for most content.
- **Label** (Bold, 11px, 1.4, uppercase): Used for table headers and eyebrows.

**The Thai Leading Rule.** Always use a line-height of at least 1.55 for body text to ensure Thai vowels and tone marks do not collide or feel cramped.

## 4. Elevation

TPSS uses a flat, layered approach to elevation. Depth is conveyed through subtle tonal shifts and borders rather than heavy shadows.

### Shadow Vocabulary
- **Ambient Low** (0 1px 4px rgba(0,0,0,0.05)): Used for the topbar and small floating elements.
- **Structural Medium** (0 2px 10px rgba(0,0,0,0.07)): Used for card hover states and popovers.

**The Flat-First Rule.** Surfaces should feel structural and rooted. Shadows are used only to respond to user interaction or to lift floating overlays (modals/popovers).

## 5. Components

Components are designed for precision, with sharp corners and clear state transitions.

### Buttons
- **Shape:** Rounded (8px radius).
- **Primary:** Mahidol Navy background with white text.
- **State:** Hover transitions to a slightly lighter navy with a subtle lift.

### Status Pills
- **Style:** Compact with a subtle background tint and high-contrast text.
- **Shape:** Full pill (999px radius).

### Cards
- **Corner Style:** Medium (10px radius).
- **Border:** Subtle grey (oklch(93% 0.008 220)) to define boundaries without adding visual weight.

## 6. Do's and Don'ts

### Do:
- **Do** use full academic titles for all instructor names.
- **Do** prioritize Thai labels for all buttons and status messages.
- **Do** maintain a clear 20px padding between major content sections.
- **Do** use tabular numbers for all numerical data in the schedule grid.

### Don't:
- **Don't** use "Side-stripe borders" on cards; use full borders or background tints instead.
- **Don't** use gradient text or decorative glassmorphism.
- **Don't** use emojis in buttons or status indicators; use formal SVG icons instead.
- **Don't** use pure black (#000) or pure white (#fff) for backgrounds or text; use the tinted neutrals instead.
