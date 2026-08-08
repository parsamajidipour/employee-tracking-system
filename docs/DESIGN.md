# Design system

One system, two surfaces: the Flutter app (`app/`) and the Nuxt panel (`panel/`).
Both read the tokens below. A colour, radius, or shadow that is not in this file
does not belong in a widget or a component.

Derived from the reference card UI: soft teal on near-white, wide radii, diffuse
shadows, and a lot of breathing room. Calm before clever.

Implementations:

- `app/lib/theme/app_theme.dart` — `AppColors`, `AppSpacing`, `AppRadii`,
  `AppShadows`, `AppTheme.light` / `AppTheme.dark`
- `panel/app/assets/css/tokens.css` — the same tokens as CSS custom properties

## 1. Colour

Teal is the only accent. Everything else is a neutral or a status colour. Never
introduce a second brand hue to make something stand out — use weight, size, or
whitespace instead.

### Brand

| Token | Light | Dark | Use |
|---|---|---|---|
| `primary` | `#2F9EC0` | `#4FBDD8` | Primary actions, active nav, selected state |
| `primaryStrong` | `#1E7F9B` | `#2F9EC0` | Pressed state, text on soft primary |
| `primarySoft` | `#DCF0F6` | `#12323D` | Chip and badge backgrounds, icon tiles |
| `primaryGradientTop` | `#4FBDD8` | `#4FBDD8` | Top of the bar/pin gradient |
| `primaryGradientBottom` | `#228DAD` | `#228DAD` | Bottom of the bar/pin gradient |

The gradient is vertical, top-light to bottom-dark, and is reserved for the logo
mark, chart bars, and the one primary button on a screen. Not for surfaces.

### Neutrals

| Token | Light | Dark | Use |
|---|---|---|---|
| `background` | `#EFF5F8` | `#0E1E26` | Screen behind the cards |
| `surface` | `#FFFFFF` | `#162C36` | Cards, sheets, nav bar |
| `surfaceMuted` | `#F6FAFC` | `#1B333E` | Inset rows, disabled fields |
| `border` | `#E2ECF1` | `#22404C` | Hairlines, input outlines |
| `textPrimary` | `#13333F` | `#EAF4F8` | Headings, values |
| `textSecondary` | `#6E8C99` | `#9DB6C1` | Labels, captions, axis text |
| `textTertiary` | `#9DB6C1` | `#6E8C99` | Placeholder, disabled |

Never pure `#000` on pure `#FFF`. `textPrimary` on `surface` is 12.1:1, and
`textSecondary` on `surface` is 4.6:1 — both pass WCAG AA. `textTertiary` is for
non-essential text only; it does not pass AA and must never carry meaning alone.

### Status

| Token | Light | Dark | Meaning |
|---|---|---|---|
| `success` | `#3FA98A` | `#57C3A3` | Tracking active, in window, synced |
| `warning` | `#D9973B` | `#E8AF5C` | Queue backing up, permission partial |
| `danger` | `#D2635E` | `#E2807B` | Denied permission, upload failing, revoked |
| `neutral` | `#8AA5B1` | `#7E9AA6` | Off shift, idle, nothing to report |

Status colour never appears alone. It is always paired with an icon and a word,
so the meaning survives colour blindness and greyscale.

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
9. The map basemap is tinted to these tokens, not left on the stock Protomaps
   flavor. Water is a muted `#cadfea`, land `#eaeef1`; a saturated map defeats the
   point of a calm palette everywhere else.
