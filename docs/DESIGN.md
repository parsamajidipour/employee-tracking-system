# Design system

One system, two surfaces: the Flutter app (`app/`) and the Nuxt panel (`panel/`).
Both read the tokens below. A colour, radius, or shadow that is not in this file
does not belong in a widget or a component.

Flat and vivid, not soft-and-calm: solid fills, no gradients, no blur, real
saturation on the primary accent and on every status colour — energetic rather
than muted, while still passing contrast. Wide radii and generous whitespace are
kept from the previous system; the low-saturation teal-on-near-white palette is
not — see `DECISIONS.md`'s "Design system: flat, vivid Tailwind palette" entry
for why and when this changed.

Implementations:

- `app/lib/theme/app_theme.dart` — `AppColors`, `AppSpacing`, `AppRadii`,
  `AppShadows`, `AppTheme.light` / `AppTheme.dark`. **Not yet updated to this
  palette** — the values below currently apply to `panel/` only. The app still
  runs the previous teal tokens until someone does the Flutter pass; until then
  the two surfaces are visibly different products, which rule 6 below says not
  to do. Flagged here instead of silently left unmentioned.
- `panel/app/assets/css/tokens.css` — the tokens as CSS custom properties,
  values sourced directly from Tailwind's default palette (`slate` for
  neutrals, `blue` for the accent, `emerald`/`amber`/`red` for status) so any
  Tailwind utility class used ad hoc still lines up with the token colours.

## 1. Colour

`primary` (blue) is the only brand accent — still true, still: never introduce
a second brand hue in UI chrome to make something stand out, use weight, size,
or whitespace instead. The one deliberate exception is data visualisation (the
per-shift route colours on the histories map), where distinct hues encode
distinct data series, not brand.

### Brand

| Token | Light | Dark | Use |
|---|---|---|---|
| `primary` | `#2563EB` (blue-600) | `#60A5FA` (blue-400) | Primary actions, active nav, selected state |
| `primaryStrong` | `#1D4ED8` (blue-700) | `#93C5FD` (blue-300) | Pressed state, text on soft primary |
| `primarySoft` | `#DBEAFE` (blue-100) | `#17305C` | Chip and badge backgrounds, icon tiles |

No gradient tokens. Fills are flat solid colour everywhere, including the logo
mark, chart bars, and buttons — this is the "flat" half of "flat and vivid."

### Neutrals

| Token | Light | Dark | Use |
|---|---|---|---|
| `background` | `#F8FAFC` (slate-50) | `#020617` (slate-950) | Screen behind the cards |
| `surface` | `#FFFFFF` | `#0F172A` (slate-900) | Cards, sheets, nav bar |
| `surfaceMuted` | `#F1F5F9` (slate-100) | `#1E293B` (slate-800) | Inset rows, disabled fields |
| `border` | `#E2E8F0` (slate-200) | `#334155` (slate-700) | Hairlines, input outlines |
| `textPrimary` | `#0F172A` (slate-900) | `#F8FAFC` (slate-50) | Headings, values |
| `textSecondary` | `#64748B` (slate-500) | `#94A3B8` (slate-400) | Labels, captions, axis text |
| `textTertiary` | `#94A3B8` (slate-400) | `#64748B` (slate-500) | Placeholder, disabled |

Never pure `#000` on pure `#FFF`. `textPrimary` on `surface` is 18.7:1 in light
mode and 17.9:1 in dark, and `textSecondary` on `surface` is 4.6:1 in light /
7.7:1 in dark — all pass WCAG AA (body text needs 4.5:1). `textTertiary` is for
non-essential text only; it does not reliably pass AA and must never carry
meaning alone.

### Status

| Token | Light | Dark | Meaning |
|---|---|---|---|
| `success` | `#059669` (emerald-600) | `#34D399` (emerald-400) | Tracking active, in window, synced |
| `warning` | `#F59E0B` (amber-500) | `#FBBF24` (amber-400) | Queue backing up, permission partial |
| `danger` | `#DC2626` (red-600) | `#F87171` (red-400) | Denied permission, upload failing, revoked |
| `neutral` | `#94A3B8` (slate-400) | `#64748B` (slate-500) | Off shift, idle, nothing to report |

Status colour never appears alone. It is always paired with an icon and a word,
so the meaning survives colour blindness and greyscale.

### Light / dark mode

The panel has a real toggle (`useTheme()` in `panel/app/composables/useTheme.ts`),
not just OS-driven colours: `system` (default, follows `prefers-color-scheme`),
`light`, or `dark`, persisted to `localStorage` and applied via
`data-theme="light"` / `data-theme="dark"` on `<html>`. A tiny inline script in
`nuxt.config.ts`'s `app.head.script` applies the stored choice before Vue mounts,
so there is no flash of the wrong theme on load.

## 2. Type

System font on both platforms — SF on iOS, Roboto on Android, the system stack in
the browser. No downloaded font: a webfont costs a round trip and a layout shift,
and buys nothing here.

| Token | Size / line | Weight | Use |
|---|---|---|---|
| `display` | 32 / 38 | 700 | The one big number on a card |
| `title` | 22 / 28 | 700 | Screen title |
| `heading` | 17 / 22 | 600 | Card title, section header |
| `body` | 15 / 21 | 400 | Default |
| `label` | 13 / 17 | 500 | Field labels, nav labels |
| `caption` | 12 / 16 | 500 | Timestamps, units, helper text |
| `overline` | 11 / 14 | 600, +0.8 tracking, uppercase | Eyebrow above a title |

Numbers that update live (queue depth, counts, times) use tabular figures so the
row does not reflow on every tick.

## 3. Spacing and shape

A 4pt base. Only use these steps: 4, 8, 12, 16, 20, 24, 32, 40, 48.

- Screen horizontal padding: 20
- Card padding: 20
- Gap between cards: 16
- Gap between a label and its value: 4
- Gap between grouped controls: 12

Radii:

| Token | Value | Use |
|---|---|---|
| `radiusCard` | 24 | Cards, sheets, dialogs |
| `radiusControl` | 16 | Buttons, inputs, list tiles |
| `radiusSmall` | 12 | Icon tiles, small badges |
| `radiusPill` | 999 | Chips, status pills, the nav bar |

## 4. Elevation

Shadows are diffuse, low-opacity, and tinted with the text colour — never a grey
or black box shadow.

| Token | Value | Use |
|---|---|---|
| `shadowCard` | `0 8 24 rgba(19,51,63,0.06)` | Resting card, app only |
| `shadowRaised` | `0 12 32 rgba(19,51,63,0.10)` | Anything floating above content |
| `shadowPressed` | `0 2 8 rgba(19,51,63,0.08)` | Pressed button |

The two surfaces separate cards differently, on purpose. In the app a card floats
on the canvas with a soft shadow, because it is one card in a short scrolling
column. In the panel a card is flat — surface, hairline border, no shadow —
because the window is already framed by a sidebar and a header, and stacking
shadows inside that frame reads as clutter rather than depth. Shadow in the panel
is reserved for things that genuinely float: the map overlay, modals, menus.

In dark mode shadows are nearly invisible; separation comes from `surface` sitting
above `background`, plus a `border` hairline. Do not raise shadow opacity to
compensate.

## 5. Motion

Motion explains a state change. If nothing changed, nothing moves.

| Token | Duration | Curve | Use |
|---|---|---|---|
| `fast` | 150ms | `easeOut` | Press, hover, ripple |
| `base` | 220ms | `easeOutCubic` | Card enter, value change, expand |
| `slow` | 320ms | `easeOutCubic` | Route transition, sheet |

Entrances stagger by 40ms per item, capped at 6 items — beyond that everything
after the sixth shares the last delay, so a long list never feels slow to arrive.

Banned: infinite looping animation on an idle screen, animated gradients,
parallax, anything driven by a scroll position, and animation on a list item that
scrolls.

Respect the platform: when `MediaQuery.disableAnimations` (or
`prefers-reduced-motion`) is set, durations collapse to zero and transitions become
instant swaps.

## 6. Performance budget

The app has to stay smooth on weak Android hardware, so the design is deliberately
cheap to render.

- No `BackdropFilter`, no blur, no glassmorphism anywhere. It is the single most
  expensive effect available and it buys nothing this UI needs.
- No shadow on a widget inside a scrolling list. Separate list rows with a
  `border` hairline or a `surfaceMuted` fill instead. Shadows belong to cards that
  sit still.
- `const` constructors everywhere they are possible, so rebuilds stay shallow.
- Gradients are limited to the logo, the primary button, and chart bars — each of
  which is drawn once and does not move.
- No opacity animation on a subtree that contains text; animate a parent's
  transform instead, which the compositor can handle without repainting.
- Images ship at the density they render at. Nothing is downscaled at runtime.

## 7. Layout and reach

The app is one-handed, on a phone, often outdoors, sometimes in gloves. Fast
access beats density.

- Minimum tap target 44x44, with at least 8 between adjacent targets.
- The primary action on a screen sits in the bottom third, within thumb reach.
- Layouts survive 320dp width and 200% text scale. Anything that would overflow
  wraps or scrolls; nothing is clipped and no text is ellipsised into meaninglessness.
- Breakpoints: `<600` phone (single column), `600-1024` tablet (two columns),
  `>1024` panel (sidebar plus content).
- Every screen states its state in words. A spinner alone is never the whole
  answer, and an empty screen always explains why it is empty.

## 8. Harmony rules

These are what keep the two surfaces looking like one product.

1. Every screen is: a title, then cards on `background`. No screen invents its own
   chrome.
2. A card is `surface`, `radiusCard`, `shadowCard`, `20` padding. Always.
3. One primary action per screen, teal and filled. Everything else is a text
   button or an outlined control.
4. Status is always the same triple: colour dot, icon, word — in that order, at
   the same size, on both surfaces.
5. Icons are outline style at 1.75 stroke, 20 in rows and 24 standalone.
6. The panel uses the same tokens at the same values. It is the same product on a
   bigger screen, not a different one.
7. Never nest a card inside a card. If content needs grouping inside a card, use a
   `surfaceMuted` block or a hairline rule, not another card.
8. Destructive actions in a table are quiet text buttons that turn `danger` on
   hover, never filled red. A row of filled buttons turns every row into an alert.
9. The live map and histories map render with Google's default Maps styling —
   Google Maps JS API doesn't support ad-hoc client-side re-tinting the way the
   previous self-hosted vector basemap did. A custom Cloud Console map style
   (via `NUXT_PUBLIC_GOOGLE_MAPS_MAP_ID`) is how this would be brought back in
   line with these tokens; not set up here, left as a follow-up. Markers,
   overlays, and the info panel chrome around the map are still fully on
   these tokens.
