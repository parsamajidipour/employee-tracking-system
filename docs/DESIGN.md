# Design system

**Panel-only as of this rewrite.** The Flutter app (`app/`) still runs the flat/vivid
tokens from the previous panel system and has not been touched — see
`app/lib/theme/app_theme.dart`. The gap this creates (two visibly different
products) is the same gap the previous version of this file flagged about itself;
bringing the app in line with the system below is unstarted follow-up work, not
an oversight. Everything in this file describes `panel/` only until that happens.

A colour, radius, or shadow that is not in this file does not belong in a
component. Implementation: `panel/app/assets/css/tokens.css` (CSS custom
properties) and `panel/tailwind.config.ts` (the Tailwind names that map to them).

This is a from-zero system, not a retint of the previous one — see
`DECISIONS.md`'s "Design system: ground-up SaaS rebuild" entry for why and when
this changed, and what it replaced.

## 1. Direction

Hybrid, not uniformly light or dark: **light surfaces for data** — tables, forms,
cards, lists — because that is what a compact, information-dense screen needs to
stay legible for long stretches. **Dark surfaces for operational context** — the
live map, the histories route map, and the left navigation rail — because those
are monitoring surfaces, not reading surfaces, and a dark map reads location data
(dots, routes, glow) far better than a light basemap competing for the same
attention. This is a deliberate split, not two half-finished themes: nothing
toggles between them, each surface just uses whichever fits what it's showing.

Density is compact: more information per screen (tables, stat rows, lists) aimed
at someone monitoring 50-150 employees, not a handful. Compact does not mean
cramped — see the touch-target rule in §7.

In practice, `map.vue` and the histories map currently render their canvas and
overlay panels on light-surface classes (`surfaceSunken`, `.surface`), not the
dark tokens below — this section describes the intended direction, not yet
where those two pages landed. Flagging it here rather than silently leaving
the doc wrong; reconciling which one is right is unstarted follow-up work.

## 2. Colour

### Brand

| Token | Value | Use |
|---|---|---|
| `primary` | `#4F46E5` | Primary actions, active nav, selected state, links |
| `primaryStrong` | `#4338CA` | Pressed/hover state on primary, text on soft primary |
| `primarySoft` | `#EEF0FF` | Chip and badge backgrounds, icon tiles, selected-row tint |
| `primarySoftDark` | `#201C42` | The above, on a dark surface (map panels) |

`primary` (indigo) is the only brand accent. Never introduce a second brand hue in
UI chrome to make something stand out — use weight, size, or whitespace instead.
The one deliberate exception is data visualisation: the per-shift route colours on
the histories map and the per-employee marker colours on the live map, where
distinct hues encode distinct data series, not brand. Those come from
`utils/mapMarker.ts`'s `employeeColor()`/`shiftColor()` hashing, not from a design
token, by design — they need more distinct values than a token file should hold.

### Light surface (data, forms, tables)

| Token | Value | Use |
|---|---|---|
| `background` | `#F7F7FA` | Screen behind everything |
| `surface` | `#FFFFFF` | Cards, tables, modals — separated by shadow, not border |
| `surfaceSunken` | `#F0F0F4` | Table header, inset rows, disabled fields, skeletons |
| `border` | `#E4E4EA` | Hairlines — used sparingly; shadow does most separation |
| `textPrimary` | `#0B0B12` | Headings, values |
| `textSecondary` | `#63636F` | Labels, captions |
| `textTertiary` | `#9A9AA6` | Placeholder, disabled, non-essential |

### Dark surface (live map, histories map, nav rail)

| Token | Value | Use |
|---|---|---|
| `surfaceDark` | `#0B0B10` | Map canvas background |
| `surfaceDarkRaised` | `#16161D` | Floating panels, nav rail, marker labels |
| `surfaceDarkHover` | `#1E1E27` | Hover/active row on a dark surface |
| `borderDark` | `#26262F` | Hairlines on dark surfaces |
| `textDarkPrimary` | `#F5F5F7` | Headings/values on dark |
| `textDarkSecondary` | `#9494A3` | Labels/captions on dark |

### Status

| Token | Value | Soft (badge bg) | Meaning |
|---|---|---|---|
| `success` | `#16A34A` | `#E8F8EE` | Tracking active, in window, synced |
| `warning` | `#D97706` | `#FDF3E3` | Queue backing up, permission partial, stale |
| `danger` | `#E11D48` | `#FDE8ED` | Denied permission, upload failing, revoked |
| `neutral` | `#9A9AA6` | `#F0F0F4` | Off shift, idle, nothing to report |

Status colour never appears alone — always paired with an icon and a word, so the
meaning survives colour blindness and greyscale (`Badge.vue`, `InlineAlert.vue`).

## 3. Type

System font stack (no webfont — a round trip and a layout shift for no gain).
Tighter and smaller than a marketing site on purpose: this is a working tool read
at a desk, not a landing page.

| Token | Size / line | Weight | Use |
|---|---|---|---|
| `display` | 28 / 34, -0.02em | 700 | Login headline, the one big number |
| `title` | 20 / 26, -0.02em | 650 | Page `h1` |
| `heading` | 15 / 20, -0.01em | 600 | Card/section title, `h2` |
| `body` | 14 / 20, -0.01em | 400 | Default |
| `label` | 12 / 16 | 500 | Field labels, table headers (as `overline`) |
| `caption` | 11.5 / 15 | 500 | Timestamps, hints, faint text |
| `overline` | 10.5 / 14, +0.06em | 650, uppercase | Table header cells |

All data — table cells, stat values, timestamps — uses `tabular-nums` so a
changing value doesn't reflow its row.

## 4. Spacing and shape

4px base. Compact control height (`--control-h`, 36px) and row height (`--row-h`,
38px) on desktop; both bump to 44/48px automatically under `(pointer: coarse)` —
touch never gets the compact desktop sizing, regardless of density preference.

Radii — smaller and more deliberate than a "friendly" system, reading as precise
rather than soft:

| Token | Value | Use |
|---|---|---|
| `radiusLg` | 14px | Cards, modals, drawers, the dark map panels |
| `radiusMd` | 10px | Buttons, inputs, table wrapper |
| `radiusSm` | 8px | Icon tiles, small buttons, badges' inner elements |
| `radiusPill` | 999px | Status pills, tab switcher |

## 5. Elevation

Two-layer shadows (a tight ambient shadow plus a softer key shadow) separate
surfaces instead of borders — `.surface` in `tokens.css`. This is the opposite of
the previous system's "flat, bordered, no shadow in the panel" rule; the new
direction leans on shadow deliberately, because it's what makes a light,
low-contrast UI still read as layered instead of flat.

| Token | Use |
|---|---|
| `shadowAmbient` | Baseline on every card/field — barely visible, just enough separation |
| `shadowKey` | Combined with ambient on `.surface` cards |
| `shadowRaised` | Modals, drawers, anything that floats above content |
| `shadowDarkKey` | Equivalent for `.surface-dark` panels (map overlays, nav rail) |

## 6. Motion

Short and functional — motion explains a state change, nothing animates when
nothing changed.

| Token | Duration | Use |
|---|---|---|
| `fast` | 120ms | Hover, press (`active:scale-[0.97]` on buttons), icon spin tick |
| `base` | 180ms | Modal/drawer enter-exit, tab switch, page transition |
| `slow` | 260ms | Marker glide on the live map |

Concrete uses already wired: page transitions (`app.vue`'s `NuxtPage transition`,
`.page-enter/leave` classes), modal/drawer enter as a scale+fade
(`.scale-in`), toast enter/exit/reorder (`TransitionGroup` in
`ToastContainer.vue`), refresh icons spin while their action is loading, a
selected live-map marker gets a ring + scale-up, an online marker gets a
continuous subtle pulse (the one deliberately "idle-looping" animation in the
system, because it encodes a real, current state — "this employee is online right
now" — not decoration).

Respects `prefers-reduced-motion` (durations collapse to ~0, existing media query
in `tokens.css`).

## 7. Layout and reach

- Minimum tap target 44×44 on touch (`(pointer: coarse)` in `tokens.css` — see §4),
  36×36 acceptable on desktop/mouse for compact density.
- Breakpoints: `<640` phone (nav becomes a full-screen sheet), `640-1024` tablet
  (nav becomes a fixed 320px sheet), `>1024` desktop (nav rail, collapsible
  between 68px icon-only and 224px icon+label).
- Every loading state is a `Skeleton`, never a bare "Loading…" string. Every empty
  state is an `EmptyState` (icon + message), never a bare paragraph.

## 8. Harmony rules

1. Light for data (tables, forms, cards), dark for operational context (maps, nav
   rail). Nothing else is dark — don't dark-mode a form or a table.
2. `primary` (indigo) is the only brand hue in chrome. The employee/shift colour
   hashing on the maps is the one named exception (§2).
3. Status is always the same triple: colour dot, icon, word — same order, same
   size, everywhere (`Badge`, `InlineAlert`).
4. Icons are outline style, 1.75 stroke, from the shared `Icon.vue` set — never a
   one-off inline `<svg>` in a page or component.
5. Table row actions reveal on hover on desktop (`opacity-0 lg:group-hover:opacity-100`)
   but stay always-visible below the `lg` breakpoint — hover-reveal is a mouse
   affordance, not something to hide behind on touch.
6. Destructive actions in a table are quiet text buttons that turn `danger` on
   hover, never a filled red button — a row of filled buttons turns every row into
   an alert.
7. Never nest a `.surface`/`.surface-flat` card inside another one. Group content
   with `surfaceSunken` or a hairline rule instead.
8. The live map and histories map pass no `mapId` to `google.maps.Map`, and
   the only `styles` rule either one carries is `utils/mapPoiStyle.ts`'s
   single `{ featureType: 'poi', visibility: 'off' }` — hiding Google's
   business/place pins because 50-150 tracked employees are already enough
   markers without restaurants and mosques competing for the same map. No
   other feature type, geometry, or colour is touched. A fuller custom
   `styles` array (first a light theme, then briefly a part-dark one) was
   tried twice and reverted both times: hand-picking a colour for every
   feature type is easy to get geographically wrong (desert rendered as green
   "natural landscape" was the specific failure), and Oman's actual
   terrain/water/vegetation only reads correctly through Google's own data.
   Employee markers, route colours, and every panel/overlay on top of the map
   are still app-styled — the base map underneath gets exactly one rule, not
   a repaint.
