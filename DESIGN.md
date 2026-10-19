---
name: Sovereign Institutional Exchange
colors:
  surface: '#f9f9ff'
  surface-dim: '#d0daf2'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f0f3ff'
  surface-container: '#e8eeff'
  surface-container-high: '#dfe8ff'
  surface-container-highest: '#d9e3fb'
  on-surface: '#111c2d'
  on-surface-variant: '#45474c'
  inverse-surface: '#273143'
  inverse-on-surface: '#ecf0ff'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e71'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#141b2c'
  on-primary-container: '#7c8498'
  inverse-primary: '#bfc6dc'
  secondary: '#535f71'
  on-secondary: '#ffffff'
  secondary-container: '#d7e3f9'
  on-secondary-container: '#596577'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#00210e'
  on-tertiary-container: '#009756'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dbe2f9'
  primary-fixed-dim: '#bfc6dc'
  on-primary-fixed: '#141b2c'
  on-primary-fixed-variant: '#3f4759'
  secondary-fixed: '#d7e3f9'
  secondary-fixed-dim: '#bbc7dc'
  on-secondary-fixed: '#101c2c'
  on-secondary-fixed-variant: '#3c4859'
  tertiary-fixed: '#70fda7'
  tertiary-fixed-dim: '#51df8e'
  on-tertiary-fixed: '#00210e'
  on-tertiary-fixed-variant: '#00522c'
  background: '#f9f9ff'
  on-background: '#111c2d'
  surface-variant: '#d9e3fb'
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-xl-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 30px
    fontWeight: '600'
    lineHeight: 38px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.005em
  title-md:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '600'
    lineHeight: 22px
    letterSpacing: 0em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
    letterSpacing: 0em
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0em
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
    letterSpacing: 0.005em
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
  tabular-num-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  tabular-num-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-sm: 1rem
  gutter-lg: 2rem
  margin: 2rem
  margin-sm: 1rem
  margin-lg: 3rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system embodies an institutional-grade financial utility—authoritative, razor-sharp, and unyielding in reliability. Tailored for high-volume traders, cross-border merchants, and liquidity providers exchanging crypto, premium gift cards, and PayPal assets into Nigerian Naira (NGN), the visual language evokes rigorous security, speed, and precision.

The aesthetic philosophy draws on **Corporate / Modern High-Density Utility** with an editorial polish:
- **Atmosphere:** Clean, clinical, and reassuringly deliberate. It discards crypto-native neon fluff in favor of sovereign banking weight, subtle structured grids, and crisp contrast.
- **Micro-Tone:** Uncompromising precision. Every monetary value, exchange rate, fee calculation, and transaction hash feels definitive.
- **Emotional Resonance:** Trustworthiness, instant liquidity, institutional compliance, and operational transparency.

## Colors

The palette balances foundational deep slate tones with a high-clarity emerald functional accent, ensuring optimal readability across complex multi-step settlement workflows.

### Core Architecture
- **Primary (`#101828`):** Deep Navy. Applied to high-emphasis typographic layers, primary action buttons, dark mode headers, and structural anchor elements.
- **Secondary (`#1D2939`):** Dark Charcoal. Used for secondary interaction states, active segment tabs, table headers, and structural chrome.
- **Accent / Success (`#12B76A`):** Emerald Green. Reserved exclusively for successful executions, affirmative CTA validations, live spread tickers, and positive rate adjustments.
- **Neutral Core (`#667085`):** Mid Slate. Powers descriptive labels, secondary metrics, field subtitles, and unselected component states.

### Surface, Canvas & Borders
- **Canvas Base:** `#F8FAFC` (Slate 50) provides a soft, low-glare canvas that differentiates high-density white cards.
- **Surface Elevation:** `#FFFFFF` (Pure White) applied to elevated cards, modular panels, drawers, and input backings.
- **Border Default:** `#E2E8F0` (Slate 200) for structural lines, dividers, and card borders.
- **Border Subtle:** `#F1F5F9` (Slate 100) for internal table row borders and sub-dividers.

### Semantic & Lifecycle State Palette
- **Completed / Confirmed:** `#12B76A` (Emerald 600) with surface fill `#ECFDF3` and border `#A6F4C5`.
- **Pending / Action Required:** `#F59E0B` (Amber 500) / `#F79009` (Amber 600) with surface fill `#FEF0C7` and border `#FEDF89`.
- **Processing / On-Chain Ingestion:** `#0BA5EC` (Blue 500) with surface fill `#F0F9FF` and border `#B9E6FE`.
- **Error / Rejected:** `#F04438` (Red 500) with surface fill `#FEF3F2` and border `#FECDCA`.
- **Muted Inactive:** `#98A2B3` (Slate 400).

## Typography

The type stack is powered entirely by **Inter**, leveraging its geometric neutrality and extensive OpenType features.

### OpenType Configuration
- **Tabular Figures (`tnum`):** Strictly enforced across all numerical representations—fiat figures (NGN ₦), crypto quantities, rates, network fees, and transaction timestamps. This prevents layout jitter during real-time order book or quote recalculations.
- **Alternative Glyphs (`cv02`, `cv03`, `cv04`):** Open 4, distinct lowercase l, and rounded quotation marks enabled to maintain maximum readability during multi-character wallet addresses and gift card PIN verification.

### Hierarchy Rules
- Headings use negative tracking (`-0.02em` to `-0.01em`) to create dense, authoritative titles.
- Labels, meta captions, and badge tags utilize subtle uppercase transformation paired with positive tracking (`+0.02em` to `+0.04em`) for clear glanceability at micro-scales.

## Layout & Spacing

The layout model is governed by a strict 8-point spatial rhythm engineered for complex, multi-tiered transactional dashboards.

### Grid Framework
- **Desktop (≥ 1280px):** 12-column responsive fluid grid with max-width containment at `1440px`. Gutters set to `1.5rem` (24px) with canvas margins at `2rem` (32px) to `3rem` (48px).
- **Tablet (768px – 1279px):** 8-column layout with `1rem` (16px) gutters and `1.5rem` (24px) margins. Exchange panels collapse into stacked, sequential vertical segments.
- **Mobile (< 768px):** 4-column layout with `1rem` (16px) margins. All execution wizards and conversion calculators span full column widths with pinned bottom action sheets.

### Density & Rhythms
- **Data Dense Layouts:** Data tables, order reviews, and address strips utilize compact internal paddings (`space-sm` to `space-md`).
- **Flow Layouts:** Step wizards, verification checklists, and onboarding sequences expand using `space-lg` to `space-xl` gaps to reduce visual strain during high-stakes actions.

## Elevation & Depth

Visual depth is achieved through **structural crisp borders paired with diffused, low-opacity neutral shadows**. Rather than deep blurry drops, this design system treats elevation as physical sheets of institutional paper resting on a technical workbench.

### Hierarchy Levels
1. **Level 0 (Canvas Surface):** Solid `#F8FAFC`. Zero elevation, hosting non-interactive background groups.
2. **Level 1 (Default Containers & Cards):** Solid `#FFFFFF`, bordered with 1px `#E2E8F0`. Shadow: `0px 1px 2px 0px rgba(16, 24, 40, 0.05)`.
3. **Level 2 (Active Dropdowns & Interactive Hover):** `#FFFFFF`, border `#CBD5E1`. Shadow: `0px 4px 6px -2px rgba(16, 24, 40, 0.03), 0px 12px 16px -4px rgba(16, 24, 40, 0.08)`.
4. **Level 3 (Modals, Step Wizard Popovers, Pin Verification Sheets):** `#FFFFFF`, border `#E2E8F0`. Shadow: `0px 8px 8px -4px rgba(16, 24, 40, 0.03), 0px 20px 24px -4px rgba(16, 24, 40, 0.08)`.
5. **Level 4 (Toasts & Persistent Sticky Settlement Bars):** `#101828` or `#FFFFFF` backed with `0px 24px 48px -12px rgba(16, 24, 40, 0.18)`.

## Shapes

The shape system employs calibrated geometry (`roundedness: 2`, matching an 8px base radius) to strike the balance between modern software design and traditional corporate banking rigor.

### Scale Application
- **Base Components (Inputs, Buttons, Asset Pills, Tabs):** `0.5rem` (8px). Delivers a clean, structural outline without feeling sterile.
- **Group Containers (Cards, Table Modules, Deposit Cards):** `0.75rem` (12px) to `1rem` (16px) for outer container frames.
- **Micro-Indicators (Status Badges, Indicator Dots):** Fully rounded pill geometry (`9999px`) to visually isolate status metadata from actionable form buttons.

## Components

### Buttons
- **Primary:** Background `#101828`, text `#FFFFFF`, font weight 600. Hover: `#1D2939`. Active: `#0B1220`. Full focus-ring: 2px `#FFFFFF` inner, 2px `#101828` outer.
- **Secondary / Outlined:** Background `#FFFFFF`, 1px solid border `#D0D5DD`, text `#344054`. Hover: `#F8FAFC` surface with border `#98A2B3`.
- **Accent (Trade Execution):** Background `#12B76A`, text `#FFFFFF`. Hover: `#0E9F5D`. Active: `#0A7B47`.
- **Destructive:** Background `#F04438`, text `#FFFFFF`. Hover: `#D92D20`.

### Form Inputs & Selectors
- **Input Wrapper:** Height 44px, surface `#FFFFFF`, 1px solid border `#D0D5DD`, corner radius 8px, padding 0 14px.
- **States:** Focus sets border to `#101828` with a 3px outer halo in `rgba(16, 24, 40, 0.08)`. Error sets border to `#F04438` with halo in `rgba(240, 68, 56, 0.12)`.
- **Currency Affix Blocks:** Structural, tabular right-aligned currency signifiers (`USD`, `USDT`, `NGN`) enclosed in a neutral tinted container (`#F1F5F9`) with monospaced precision.

### Asset Selection Pills
- Segmented control pills used to switch between settlement currencies (e.g., BTC, ETH, USDT, Steam, Apple, PayPal).
- **Default State:** Background `#F2F4F7`, text `#344054`, border transparent.
- **Selected State:** Background `#FFFFFF`, text `#101828`, 1px solid `#CBD5E1`, elevated with shadow `0px 1px 3px rgba(16, 24, 40, 0.1)`. Includes high-contrast asset icon and live exchange rate tick.

### Deposit Address Cards & Copy Actions
- Encased within a `#F8FAFC` panel bordered with `#E2E8F0`.
- Contains:
  1. High-contrast QR code container (120x120px white canvas with 1px border).
  2. Monospaced wallet address display formatted with `font-family: monospace` or `Inter` tabular figures, middle-truncated if on mobile.
  3. Integrated "Copy" secondary button with instant feedback tooltip transition ("Copied!" in `#12B76A` accent).
  4. Prominent warning strip detailing the target network (e.g., "Send only USDT TRC-20 to this address").

### Proof Upload Dropzone (Receipts & Gift Cards)
- Dashed 1.5px border in `#CBD5E1` around a `#F8FAFC` drop area. 
- Center-aligned asset upload icon, bold headline "Click to upload proof of payment", subtext "PNG, JPG or PDF up to 10MB".
- Active drag-and-drop state switches background to `#ECFDF3` with a `#12B76A` border.

### Status Timeline Stepper
- Sequential transactional progress tracker:
  1. *Order Created* → 2. *Payment / Transfer Detected* → 3. *Network Confirmations (3/12)* → 4. *NGN Dispatched to Bank*.
- **Nodes:** 24px circles. Completed steps render in filled `#12B76A` with a white checkmark. Current step pulses with a double-ring `#0BA5EC` processing badge. Upcoming steps remain `#E2E8F0` with `#98A2B3` numerical markers. Connecting lines use 2px solid bars.

### Institutional Data Tables
- **Header:** `#F8FAFC`, uppercase label typography, height 40px, bottom border 1px solid `#E2E8F0`.
- **Rows:** Alternating clean white rows with hover tint `#F8FAFC`, minimum height 52px.
- **Numeric Alignment:** All financial amounts, exchange rates, and transaction IDs are right-aligned and render with `font-feature-settings: "tnum" 1`. Statuses are wrapped in rounded pill badges with their corresponding semantic color fills.