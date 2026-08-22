# Decisions

Lightweight decision log. Newest entries at the bottom. This is not a spec —
`docs/SPEC.md` is the source of truth for behavior; this file is the "why"
behind structural choices that aren't obvious from reading the code.

## Admin UI is Nuxt, not Blade or Inertia

**Decision.** The admin panel is a separate Nuxt + Tailwind app (`panel/`),
not server-rendered Blade views or an Inertia-glued SPA inside the Laravel
app. `api/` (the renamed former `panel/`) is Laravel, API only — no views, no
Vite/Tailwind frontend tooling, no `resources/`. (Which Nuxt major version —
see the entry below.)

**Why.** Directed change, not derived from a technical constraint in this
repo. The consequence that matters for everything downstream: the admin UI
and the backend are now two separately deployable applications talking over
HTTP, not one monolith. Auth, CORS, and session/cookie handling all had to
become explicit instead of implicit.

**Consequences.**
- `api/` needed Sanctum for SPA (cookie-based) authentication — see below.
- CORS had to be configured explicitly (`api/config/cors.php`), since
  requests now cross an origin boundary that didn't exist before.
- The mobile app (`app/`, phase 2) was already going to be a separate HTTP
  client; the panel now follows the same shape. `api/`'s API surface is the
  single contract both clients consume — no more "the admin UI can just read
  Eloquent models directly."

## Sanctum SPA (cookie) auth, not API tokens

**Decision.** The panel authenticates via Sanctum's stateful SPA flow —
CSRF cookie, session cookie — not personal-access-token Bearer auth.

**Why.** The panel is a first-party client on a known, fixed set of origins
(`api/config/cors.php`'s `CORS_ALLOWED_ORIGINS`, `api/config/sanctum.php`'s
`SANCTUM_STATEFUL_DOMAINS`), not a third-party integration. Session cookies
mean no token storage/rotation/expiry concerns on the frontend, and the
browser handles the cookie jar for us. Bearer tokens remain available on the
same `api:sanctum` guard for the mobile app later, which is not a
browser-cookie client — the two auth modes coexist on the same routes without
extra code, which is the point of Sanctum's design.

**Consequences.** Any new panel-facing endpoint must stay behind
`auth:sanctum` and assume a session, not a token, unless deliberately built
for mobile too. `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` must
list the exact origin(s) the panel is actually served from — a mismatch
fails closed (no cookie, 419 on POSTs), not open.

## Nuxt runs on the host in dev; Docker only builds it for prod

**Decision.** `docker-compose.yml` (dev) covers `postgres`, `redis`, and
`api` only. The panel is started with `npm run dev` on the host.
`panel/Dockerfile` still exists, but the only thing that references it is
`docker-compose.prod.unfinished.yml`, which is not used in dev — see the
next entry for why it's named that.

**Why.** A containerized Nuxt dev server adds a rebuild-image step to every
frontend change for no benefit in a single-developer/single-VPS-scale setup
where the panel doesn't need to be networked with the other services during
development — it only needs to reach `api/`'s already-host-mapped port,
which `npm run dev` can do directly. Running it on the host keeps Vite's HMR
fast and avoids container-networking indirection for something that's pure
frontend iteration.

**Consequences.** `panel/.env`'s `NUXT_PUBLIC_API_BASE` must point at
`api/`'s host-mapped port (not the Docker service name — the browser can't
resolve that). Whatever port the panel runs on (`PANEL_PORT`, default 3000)
must match `api/`'s `SANCTUM_STATEFUL_DOMAINS` / `CORS_ALLOWED_ORIGINS`, or
login will fail CORS/CSRF checks.

## docker-compose.prod.unfinished.yml is named that on purpose

**Decision.** The file adding a containerized `panel` service on top of the
base stack is named `docker-compose.prod.unfinished.yml`, not
`docker-compose.prod.yml`, and opens with a "NOT PRODUCTION-READY" banner.

**Why.** `panel/Dockerfile` runs `npm run dev` — the Nuxt dev server, not a
production build. A file plainly named `docker-compose.prod.yml` invites
someone to run it against a real deploy without reading further; the `npm
run dev` process inside it would then serve dev-mode HMR/error-overlay
code in production. Renaming it and banner-commenting it is the cheap
insurance against that, until the Dockerfile is actually rewritten to run
`nuxt build` and serve `.output/` with a production Node process (plus
deciding what sits in front of it — reverse proxy, TLS termination, etc.).
That rewrite is out of scope here.

**Consequences.** Don't rename it back to `docker-compose.prod.yml` until
`panel/Dockerfile` actually performs a production build. If you do that
work, update this entry and the one above accordingly.

## panel/ runs Nuxt 4, not 3 — a version-skew bug forced the move

**Decision.** `panel/` pins `nuxt@^4.5.2`, not `^3.x`. TypeScript is used
normally throughout `panel/app/` (`lang="ts"`, `.ts` composables).

**Why.** `nuxt@3.21.11`'s installed dependency tree has a real version
skew: `nuxt` itself depends on `@nuxt/kit@3.21.11` exactly, but transitive
deps (`@dxup/nuxt`, `@nuxt/devtools` / `@nuxt/devtools-kit`) pull in
`@nuxt/kit@4.5.2`. `nuxt`'s own root `.nuxt/tsconfig.json` ends up generated
in the *newer* split-file shape — it references `./tsconfig.app.json` — but
the actually-hoisted 3.21.11 `@nuxt/kit` only ever writes the *old* single
`tsconfig.json`. Result: `.nuxt/tsconfig.app.json` never exists, and any
`.ts` file or `lang="ts"` block crashes the Vite dev server with `ENOENT` on
that missing file the moment it needs TS type-stripping.

Two fixes were possible: force `@nuxt/kit` to a single version everywhere
(via npm `overrides`), or move `nuxt` itself to the 4.x line the transitive
deps already expect. The `overrides` attempt (pinning `@nuxt/kit` down to
3.21.11 across the tree) didn't take cleanly — npm left the three transitive
copies at 4.5.2 and reported them "invalid" rather than replacing them.
Moving to `nuxt@^4.5.2` removed the skew outright: `@nuxt/kit@4.5.2` is now
what's actually hoisted and used by `nuxt`'s own prepare step, so all five
`.nuxt/tsconfig.*.json` files get written consistently, and `npm run dev` /
`nuxt typecheck` both run clean.

**Also needed for typecheck:** `nuxt typecheck` requires `vue-tsc`, which
requires `typescript` to still expose its pre-rewrite `./lib/tsc` subpath
export. `npm install -D typescript` grabs whatever is tagged `latest`, which
was `7.x` (TypeScript's native/Go-based rewrite) at the time — its package
layout doesn't have that subpath, so `vue-tsc` fails with
`ERR_PACKAGE_PATH_NOT_EXPORTED`. `panel/package.json` pins
`typescript@5.9.3` (last stable pre-7.x release) as a devDependency
specifically for `vue-tsc` compatibility.

**Consequences.** If bumping `nuxt` past `4.5.2`, re-check `npm ls
@nuxt/kit` for a reintroduced skew before assuming TS still works. If
`vue-tsc` ever ships a release compatible with `typescript@7.x`'s new
export layout, the `typescript@5.9.3` pin can be dropped.

## Authorization is capability-based, not a role hierarchy — and admin holds both capabilities

**Decision.** `App\Enums\UserRole` has no rank/comparison method. Access is
decided by `App\Enums\Capability` (`manage-schedules`, `view-locations`),
via `App\Enums\UserRole::can()` and enforced by `App\Http\Middleware\
EnsureCapability`. `manage-schedules` is granted to `admin` and `hr`;
`view-locations` to `admin` and `supervisor`. Neither role inherits the
other's capability.

**Why.** An earlier version used a linear `atLeast()` rank comparison
(employee < supervisor < hr < admin), which meant `hr` — ranked above
`supervisor` — automatically passed any `role:supervisor` gate, including
future location-viewing endpoints (the live map). That breaks the
separation of duties this system depends on per `CLAUDE.md`: the person who
sets working hours should not be the person watching people move. Two
independent capabilities, each with its own explicit grant list, make that
unrepresentable by construction — there's no rank to accidentally satisfy.

**Consequences.** `admin` is the one role holding both capabilities, which
is itself a concentration of exactly the power this design otherwise keeps
split. Not solved here — deliberately left as a known, accepted risk rather
than invented a third role or an approval workflow with no product
requirement driving it. If `admin`'s dual access becomes a real concern
(e.g. once the live map ships), revisit by splitting `admin` into
narrower roles or requiring a second admin's approval for one of the two
capabilities — but only when something actually asks for it.

Any new panel/admin route must be added to exactly one of the two
`Route::middleware('capability:...')` groups in `routes/api.php`, never
gated by role name or rank directly.

## Live map tiles: Google Maps JS API, not self-hosted PMTiles

**Decision.** Both map pages (`panel/app/pages/map.vue` and
`panel/app/pages/employees/[id]/histories.vue`) render with the Google Maps
JavaScript API (loaded via `@googlemaps/js-api-loader`), authenticated with
a key in `panel/.env`'s `NUXT_PUBLIC_GOOGLE_MAPS_API_KEY`. `maplibre-gl`,
`pmtiles`, and `@protomaps/basemaps` are removed from `panel/package.json`;
`api/app/Http/Controllers/BasemapController.php`, its route, the
`scripts/install-basemap.sh` extract step, and the basemap volume mount in
`docker-compose.prod.yml` are deleted.

This supersedes the entry below (self-hosted PMTiles), not just amends it —
including the exact consequence that entry existed to remove: the map
viewport is once again visible to a third party (Google), on every request
for tiles.

**Why.** Directed change, requested explicitly and knowingly against the
self-hosting rationale below — the requester supplied a Google Cloud API
key and asked for the reversal in the same breath as being told what it
gives up. Recorded here so the next person touching this file has the
"why" rather than rediscovering the tradeoff from scratch.

**Consequences.**
- **Privacy regression, accepted knowingly.** The previous entry's whole
  point was that the viewport a supervisor is looking at (which, on the
  live map, correlates with which employees are currently visible) never
  left this deployment. That is no longer true: Google's tile/geocoding
  endpoints see every map pan/zoom from the panel. No employee location
  coordinates are sent to Google — markers are drawn client-side from data
  already fetched over `api/`'s own WebSocket/REST endpoints — but the
  *viewport* itself is Google-visible again. If this deployment's privacy
  posture hardens later, revisit by reverting to the PMTiles setup this
  entry replaces (still in git history) rather than layering a proxy in
  front of Google's tiles, which likely violates Google's terms of service.
- **A real, billed dependency now exists.** `NUXT_PUBLIC_GOOGLE_MAPS_API_KEY`
  must be present and the Google Cloud project it belongs to must have
  billing enabled and the Maps JavaScript API turned on, or both map pages
  fail closed with a visible error instead of silently degrading. This is a
  genuinely new operational dependency at a project that otherwise runs
  entirely on its own VPS — track usage/billing on the Google Cloud console,
  since nothing in this repo does.
- `api/config/cors.php`'s `exposed_headers` is back to `[]` — that entry
  existed only for the pmtiles client's range-read `fetch()` calls.
- No Google Maps "Map ID" is used anywhere, and the only `styles` rule either
  map page carries is `panel/app/utils/mapPoiStyle.ts`'s single
  `{ featureType: 'poi', visibility: 'off' }`, hiding Google's business/place
  pins so they don't compete with employee markers. A fuller custom colour
  palette (`panel/app/utils/lightMapStyle.ts`) was tried and reverted in a
  later session: a hand-picked colour for every feature type is easy to get
  geographically wrong (desert rendered as green "natural landscape"), and
  the ongoing cost of maintaining a from-scratch style is not worth it
  against just using Google's own map. Employee markers, labels, and every
  other overlay stay app-styled; the base map's geometry and colour do not.

## Both maps reverted to self-hosted PMTiles; Google Maps removed from the panel entirely

**Decision.** Both `panel/app/pages/employees/[id]/histories.vue` and
`panel/app/pages/map.vue` render with MapLibre GL JS against the self-hosted
`oman.pmtiles` extract again — the exact setup the Google Maps entry above
superseded — restored from git history (`0e6fc25^`) rather than rebuilt from
scratch, with each page's later feature work ported onto it: histories keeps
shift grouping/selection, hover tooltips, decimated dot markers, and the
`anchorWalk` line simplification; the live map keeps the pulsing/selectable
employee marker, staleness-based colour, glide-on-move animation, and the
online-count/detail-panel UI. `api/app/Http/Controllers/BasemapController.php`,
its route, `scripts/install-basemap.sh`, its call in `scripts/deploy.sh`, and
the basemap volume mount in `docker-compose.prod.yml` are all restored.

Google Maps is fully removed, not left coexisting: `@googlemaps/js-api-loader`
and `@types/google.maps` are out of `panel/package.json`;
`useGoogleMaps.ts` and `utils/mapPoiStyle.ts` are deleted;
`NUXT_PUBLIC_GOOGLE_MAPS_API_KEY` is gone from `nuxt.config.ts`'s
`runtimeConfig`, `docker-compose.prod.yml`'s `panel` service environment, and
`panel/.env.example`. `utils/mapMarker.ts`'s Google-specific
`getEmployeeMarkerOverlayCtor` (a `google.maps.OverlayView` subclass, built
lazily because that base class doesn't exist until the Maps JS script loads)
is replaced by `createEmployeeMarker`, a plain factory function wrapping
MapLibre's `Marker` — no lazy construction needed since MapLibre is a real
npm ES module, not a runtime-injected global, and no manual
projection/`draw()` step since `Marker` reprojects itself on every map
render. This was done in the same session as the histories-only version of
this decision, which briefly existed as a real deployed intermediate state
(see the deploy history around commits `cca226a`/`fc0acdd`) before being
completed here — that earlier revision of this entry describing `map.vue` as
"deliberately not touched for now" is superseded by this one, not preserved
alongside it, since it was never referenced from anywhere else and leaving
it would misdescribe the current, completed state.

**Why.** Directed change: the per-request Roads-API-style "snap the line to
the street" behavior the requester actually wanted turns out to need a
routing/map-matching layer, and doing that against Google's stack means
enabling and paying for Google's Roads API on top of the Maps JS API already
in use. The requester chose to move both maps off Google first (removing
that ongoing cost and putting map data on infrastructure this deployment
already controls), then build map-matching on top of the self-hosted OSM
extract next — not to call `tile.openstreetmap.org` directly, which the
superseded entry above already documents as against OSM's usage policy for
an app like this and was the reason PMTiles replaced it the first time. On
seeing the histories-only version working, the requester asked for the live
map too rather than leaving the two pages on different map stacks.

**Consequences.**
- The Google Maps decision's privacy tradeoff is fully reversed again: no
  panel page sends its viewport to Google any more, only to this
  deployment's own basemap route — back to the self-hosted PMTiles entry's
  original privacy posture.
- No Google Cloud project, API key, or billing is a dependency of this
  codebase any more. `panel/.env`'s `NUXT_PUBLIC_GOOGLE_MAPS_API_KEY` (if
  still present locally) is simply unused now; nothing reads it.
- The map style on both pages comes from `@protomaps/basemaps`'s
  `namedFlavor('light')` passed to `layers()`, not a hand-authored style —
  no bespoke vector style to maintain layer-by-layer (the flat/vivid palette
  entry above already noted the cost of that path for Google's base map and
  chose not to pay it there either). `'dark'` was tried first and reverted
  within the same session at explicit request: the actual ask was for the
  map to look like ordinary, recognisable OpenStreetMap, which reads as
  light, not dark. `docs/DESIGN.md` rule 8 and its §1 "hybrid" framing are
  rewritten accordingly — dark surface is now scoped to the nav rail and to
  floating chips/panels *on top of* a map, not the map canvas itself.
- Start/End terminal markers (histories) and the employee marker (live map)
  use MapLibre's DOM-based `Marker` with a plain HTML div rather than a
  `symbol` text layer — the style object here doesn't set a `glyphs` URL
  (neither did the pre-Google version this restores), so vector text labels
  aren't available without pulling in a font/glyph server dependency nobody
  has asked for yet. Small per-point dots on the histories page use a
  `circle` GeoJSON layer instead of individual DOM markers for the same
  reason `decimatePoints` exists at all — hundreds of DOM nodes would be the
  expensive path `docs/DESIGN.md` says to avoid.
- `oman.pmtiles` itself (the ~80MB extract in `api/storage/app/basemap/`,
  git-ignored) was never deleted from the deploy host or this dev machine
  when the Google Maps switch happened — only the code referencing it was
  removed. `scripts/install-basemap.sh`'s existing-file/size-floor check
  means restoring the call in `deploy.sh` is a no-op against a file that's
  already there, and a real extract if it isn't.
- Next step, not done here: map-matching the recorded points to the road
  network for display (the actual ask — "the line should move along the
  street like Google Maps' routes do"), most likely a self-hosted OSRM
  instance against the same Oman extract, kept off Google entirely,
  consistent with this decision's direction.

---

<details>
<summary>Superseded: self-hosted PMTiles extract (kept for history — do not follow this section)</summary>

**Decision.** The live map rendered with MapLibre GL JS against a single
PMTiles archive (`api/storage/app/basemap/oman.pmtiles`, a Protomaps
basemap extract clipped to Oman's bounding box, zoom 0–14), served by
`api/`'s own `GET /api/basemap/oman.pmtiles` route
(`App\Http\Controllers\BasemapController`) and read client-side with the
`pmtiles` package's `Protocol` handler plus `@protomaps/basemaps`' vector
style layers. No new container: `response()->file()` on a `BinaryFileResponse`
already gets `Range`-request handling for free from Symfony, which is all
the PMTiles HTTP-range-read model needs.

This replaced an earlier entry (OSM's standard raster tile servers) — the
"viewport leaks to a third party" consequence that entry accepted is what
this decision removed, and what the Google Maps decision above reintroduces.

**Why.** `tile.openstreetmap.org`'s usage policy doesn't permit embedding
it in an application like this one, and OSM's own infrastructure will
rate-limit or block it under any real load. PMTiles made self-hosting cheap
enough to do immediately: one file, range-read over plain HTTP, no
tile-serving process, no cache layer, no per-tile routing — the file itself
*is* the server-side state.

**How the extract was built.** Protomaps publishes a daily-updated
planet-wide basemap PMTiles archive at `https://build.protomaps.com/
<YYYYMMDD>.pmtiles` (~120GB). PMTiles' layout supports extracting a
bounding box from a *remote* archive via HTTP range requests alone —
nothing close to 120GB is downloaded. The `go-pmtiles` CLI's `extract`
subcommand did this for Oman's bbox in about 80MB / a few dozen HTTP
requests total.

</details>

## Multi-team is deferred, not removed

**Decision.** This deployment seeds exactly one team ("Default", `Asia/
Muscat`) and attaches every employee to it automatically — there is no team
picker anywhere in the panel (`teams.vue` is deleted, `shift-templates.vue`
sends `team_id` silently instead of showing a select). The underlying
concept is untouched: `teams` is still a real table, `Team.timezone` still
feeds `App\Services\ShiftWindowResolver`, `shift_templates` still belong to
a team, and `TeamController`'s CRUD endpoints are still live at
`/api/v1/teams` — just unlinked from the UI, not deleted.

**Why.** The product is a single company right now. A picker with exactly
one always-selected option is UI for a problem that doesn't exist yet;
removing the *concept* of a team instead would mean redesigning the
schedule domain from scratch the day a second company or site actually
needs one. Keeping the schema and API intact while hiding the picker is
the cheap, reversible version of "not now."

**Consequences.** `database/migrations/..._seed_default_team_and_backfill_users.php`
is a one-time data migration, not a seeder — it runs on every deploy via
`migrate --force`, so the default team and the backfill both exist
unconditionally, not just in dev environments that happen to run
`db:seed`. Un-deferring multi-team means adding the picker back to
`teams.vue`'s (or a successor's) UI and to `shift-templates.vue` and the
employee-creation flow — the API already supports it. Nothing in the
resolver, the gate, or the migrations changes.

## One active device per employee — company-issued phones, not BYOD

**Decision.** `App\Services\DeviceService::login()` refuses a second device
login for an employee while a first one is still active (`devices` has a
partial unique index on `employee_id` WHERE `revoked_at IS NULL`). There is
no "log in on a new phone automatically revokes the old one" path — an hr/
admin must explicitly revoke via the panel (`DELETE /api/v1/employees/
{employee}/device`) before a new device can log in for that employee.

**Why.** The phones running the tracking app are company-issued, not the
employee's own (SPEC section 9's open question 4 is answered by this: not
BYOD). A second device attempting to log in while the first is still
active is either a lost/replaced phone or something actually wrong — not a
normal, expected event like "I got a new personal phone" would be on a
BYOD deployment. Refusing by default and requiring an explicit revoke
means every device change leaves a trail (the old `devices` row stays,
marked `revoked_at`, alongside the new one) instead of silently rotating.

**Consequences.** If phones ever become employee-owned (BYOD) in a later
phase, this policy needs revisiting — auto-revoking the old device on a
new login might become the right default instead of refusing outright.
Losing a phone without panel access to revoke it first means that
employee can't get a new device logged in until an hr/admin does it —
acceptable friction at this project's scale (50–150 employees, an hr/admin
always reachable), not something to work around with self-service revoke
from the app itself.

## The employee roster (GET /employees) moved to manage-schedules, not view-locations

**Decision.** `GET /api/v1/employees` now requires `manage-schedules`
(hr/admin), not `view-locations` (supervisor/admin) as it did before this
change. A supervisor session gets 403 on it.

**Why.** The response now carries phone numbers, usernames, active status,
and a device identifier/last-seen time (`App\Http\Controllers\Api\V1\
EmployeeController::index()`) — account data, not location data. A
supervisor's capability is specifically about seeing *where* employees
are, not their phone numbers or whether their device was recently
revoked. The live map never called this endpoint anyway: it gets employee
names from `/positions`, and the detail panel's window/session data from
`employees/{id}/window` and `employees/{id}/session`, both still
`view-locations`-gated and unchanged. This also fixes a pre-existing
inconsistency: `employees/{id}.vue`'s schedule-editing mutations
(`employee-shifts`, `shift-exceptions`) were already `manage-schedules`-
gated, so a supervisor could load that page but never save anything on
it — the whole employee-management surface is consistently hr/admin-only.

**Consequences.** Any future supervisor-facing need for an employee list
(e.g. searching by name in a UI element that doesn't show account data)
should hit `/positions` or a new, narrower endpoint — not this one.

## A seeder, not a migration, owns the default admin account

**Decision.** `database/seeders/AdminUserSeeder.php` creates (or repairs,
via `updateOrCreate`) a default admin account, credentials from
`SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD` (documented default:
`test@example.com` / `password`). Wired into `DatabaseSeeder`, so
`php artisan db:seed` or `migrate:fresh --seed` always leaves a working
admin login behind. It refuses to run under `APP_ENV=production` — a
hard-coded environment check inside the seeder itself, not just reliance
on Artisan's interactive confirm-in-production prompt, since that can be
bypassed with `--force` by a deploy script without anyone specifically
deciding "yes, seed a documented-default admin account into production
today."

**Why.** A migration was the wrong place for this: migrations are schema,
run unconditionally in every environment including production, and
`CLAUDE.md` requires them to never be edited once shipped — none of that
fits "create an account whose credentials are public knowledge." Before
this, recreating a missing/broken admin account after a database reset
was a manual `tinker` one-liner, redone by hand each time — it cost real
debugging time more than once (mistaken for an actual login bug rather
than recognized instantly as "the seed step didn't run").

**Consequences.** The stock `DatabaseSeeder`'s old factory-created "Test
User" is gone — it defaulted to `role=employee`, which was never actually
a usable admin login to begin with. Anyone relying on that specific
behavior (unlikely; nothing in this codebase did) needs the new
`AdminUserSeeder` path instead.

## In-app update checking is a server-hosted release table, not a store

**Decision.** `app_releases` (`AppRelease`) holds one row per uploaded
`.apk`: `version_code`, `version_name`, a private-disk `file_path`,
`release_notes`, `is_mandatory`. `GET /api/v1/app/latest-version` and
`GET /api/app-releases/{id}/download` are public and unauthenticated —
same reasoning as `BasemapController`: the mobile app needs to check for
and fetch an update before or without a valid session, and neither
response exposes anything sensitive. Uploading a release is gated behind
a new `Capability::ManageReleases`, admin-only, from the panel.

**Why.** The app is a single sideloaded universal APK (see `CLAUDE.md`,
Android packaging), not Play Store-distributed, so there is no platform
update channel to lean on. The team asked for a real update-check
mechanism, not just a version bump, so the phone needs somewhere to ask
"is there something newer than what I'm running" and somewhere to
download it from. A dedicated table (not, say, files dropped in
`storage/app/releases` and inferred from directory listing) keeps
`version_code` uniqueness enforced by a DB constraint and lets the panel
show/retract past releases without touching the filesystem directly.

**Consequences.** `version_code` is admin-supplied at upload time, not
parsed out of the APK server-side — parsing a binary Android manifest
would need a package outside the `laravel/*`/`spatie/*` allowlist for no
real benefit, since the admin already knows the number from the build
that just ran. The admin panel and `app/pubspec.yaml`'s `+build` number
must be bumped together by hand; nothing enforces that they match beyond
the admin typing the right value into the upload form.

## Design system: flat, vivid Tailwind palette, not the calm teal system

**Decision.** `docs/DESIGN.md`'s colour tokens (and `panel/app/assets/css/
tokens.css`) moved from the original low-saturation teal-on-near-white "calm"
palette to a flat, higher-saturation palette sourced directly from Tailwind's
default scale (`slate` neutrals, `blue` primary, `emerald`/`amber`/`red`
status). No gradients anywhere, including the logo/button gradient the old
system reserved. The panel also gained a real light/dark toggle
(`useTheme()`), not just `prefers-color-scheme`.

**Why.** Directed change, explicitly requested against the documented "calm
before clever" rationale — recorded here, as with the Google Maps entry
above, so the tradeoff is visible to whoever next touches this file rather
than looking like an unreviewed drift from the original design intent.

**Consequences.**
- `app/lib/theme/app_theme.dart` (the Flutter app) was **not** updated to
  match. `docs/DESIGN.md` flags this explicitly. Until someone does the
  Flutter pass, the app and the panel are visibly different palettes, which
  contradicts this file's own "one system, two surfaces" framing and rule 6
  ("the panel uses the same tokens at the same values"). Do the Flutter side
  before calling this migration finished.
- The map basemap-tinting rule (old rule 9) no longer applies as written —
  Google Maps JS API doesn't expose the same client-side vector re-tinting
  PMTiles/MapLibre did. See `docs/DESIGN.md` rule 9's replacement text.
- Contrast was re-checked against the new pairs (documented in
  `docs/DESIGN.md`'s Neutrals table) rather than assumed from the old ratios,
  since a saturation change can silently break a ratio that used to pass.

## Design system: ground-up SaaS rebuild, superseding the flat/vivid palette above

**Decision.** The flat/vivid Tailwind palette above was itself replaced, this
time by an explicit request for a full ground-up redesign rather than another
retint: "do not preserve the current visual design... think redesign, not
polish." `docs/DESIGN.md` was rewritten wholesale (not edited section-by-section)
to a hybrid light/dark system — light surfaces for tables/forms/cards, dark
surfaces reserved for the live map, histories map, and nav rail — with an indigo
(`#4F46E5`) accent, compact density, and every component under
`panel/app/components/` rebuilt rather than restyled in place. The
light/dark **toggle** from the previous decision (`useTheme()`) was removed
entirely, not carried forward: this system isn't user-switchable light/dark, it's
a fixed hybrid where each surface uses whichever fits what it's displaying.

**Why.** Explicit product decision, made with full knowledge of what it discards
— recorded here for the same reason as the entry above: so the next person to
touch this doesn't read the previous decision's palette values as still current,
and doesn't reintroduce a theme toggle assuming one is expected.

**Consequences.**
- Same Flutter gap as before, now wider: `app/lib/theme/app_theme.dart` was
  already out of sync with the flat/vivid palette, and is now out of sync with
  this hybrid system too. `docs/DESIGN.md` flags this at the top of the file
  again. Two design-system generations behind, not one — worth doing the
  Flutter pass before letting a third generation land only on the panel.
- The live/histories map basemap situation actually improved instead of staying
  a documented gap: dropping `mapId` from both `google.maps.Map` constructors
  and passing an inline dark `styles` array (`utils/darkMapStyle.ts`) restored
  client-side map tinting, which the flat/vivid entry above had marked as no
  longer possible under a Cloud Console Map ID. The trade-off (no Map ID means
  no Advanced Markers / cloud-managed styling) is now the live constraint —
  see `docs/DESIGN.md` rule 8.
- `ShiftPicker.vue` and `StatCard.vue`, introduced earlier in the same session
  as scoped extractions, were kept as components and restyled rather than
  removed — the extraction was structurally correct, only the visual layer
  changed underneath them.

## Employee login: email/phone replaces username; no forced HTTPS on generated URLs

**Decision.** `App\Services\DeviceService::login()` now looks employees up by
`email` OR `phone` (a single `identifier` field on `/api/v1/device/login`),
not `username`. `StoreEmployeeRequest`/`UpdateEmployeeRequest` require both
`email` and `phone` (each unique) for every employee going forward. The
`username` column stays in the `users` table, unused, rather than being
dropped — it's cheap to leave and a `DROP COLUMN` against live production data
isn't worth the risk for a column nobody reads anymore. Existing employees
created before this change keep whatever `username` they had, but it no
longer authenticates anything — an admin must add an email (and now-required
phone) via the new `PUT /api/v1/employees/{id}` endpoint before that employee
can sign in again on the app. This was an explicit, informed choice (offered
against a safer "keep username as a fallback" alternative) — nobody should
"fix" it later by quietly reintroducing username login as a workaround.

Separately, `AppServiceProvider::boot()` no longer calls
`URL::forceScheme('https')` in production. This deployment has no TLS
termination anywhere (confirmed: `http://` on port 8000 answers, `https://`
on the same port doesn't even complete a TCP/TLS handshake) — the line was
forcing every `route()`-generated absolute URL, including app-release
`download_url`, to a scheme the server can't actually serve. It silently
broke both the panel's "Download" link and the Android app's in-app update
download; only the Android app's failure was visibly reported (a browser's
retry/redirect behavior probably logged the panel's).

**Why.** A workforce-tracking app's login is exactly the kind of change
CLAUDE.md says to ask about rather than assume — existing production
employees can be locked out by getting this wrong, so the tradeoff was put to
the user directly rather than picked silently.

**Consequences.**
- `EmployeeWelcomeMail` and `EmployeePasswordChangedMail`
  (`App\Mail\`) are new, and both include the plaintext password in the email
  body by explicit product request. `resources/views/` still does not exist
  in this repo (`start-container`'s startup checklist fails the deploy if it
  does — "API-only build contains no server views") so both Mailables build
  their HTML as a PHP string via `Content::make(htmlString: ...)` and a small
  `BuildsBrandedEmail` trait, never a Blade view.
- `MAIL_MAILER=log` in `api/.env` — emails queue and "send" successfully but
  only ever land in the Laravel log, not a real inbox, until a real mailer is
  configured. Implemented and tested as far as the queue boundary; actual
  delivery needs real SMTP/API credentials in production, which is an
  operations step outside this session's scope.

## Caching: app-release lookups only, deliberately not window/shift resolution

**Decision.** `AppReleaseController::latest()` is cached (Redis, `app-release:latest`,
invalidated on upload/retract). `ShiftWindowResolver` and the `me/window` read path
stay uncached, even though the app now polls both more often (every ~5 minutes,
tied to the upload cycle — see the Flutter changes in the same commit).

**Why.** The release lookup is safe to cache: it only changes on an explicit admin
action and carries no time-sensitivity. Window resolution is the opposite — it's
this project's most invariant-heavy code path (`CLAUDE.md` #1–#8), computed fresh
against `now()` on every call, with edge-case tests for grace periods, midnight
crossings, and exception vetoes. Caching it risks serving a stale in/out-of-window
verdict, which is exactly the class of bug the "no manual refresh needed" fix this
session was trying to close, not reopen. At this app's scale (50–150 employees
polling every few minutes) the resolver's query cost was never the bottleneck
being asked about.

## Map tiles: OSM's own `tile.openstreetmap.org`, not self-hosted PMTiles — a knowing tile usage policy exception

**Decision.** Both map pages now source their basemap directly from OSM's
official raster tile server (`https://tile.openstreetmap.org/{z}/{x}/{y}.png`),
via a plain MapLibre GL `raster` source/layer — no vector style, no
`namedFlavor`, no CSS filter or overlay of any kind on top of it. This
supersedes the self-hosted PMTiles entry above, which itself only stood for
part of this same session: Google Maps → self-hosted PMTiles (MapLibre GL +
`@protomaps/basemaps`, `'dark'` then `'light'` flavor) → this. The self-hosted
PMTiles infrastructure that entry restored is removed again, all in the same
session: `api/app/Http/Controllers/BasemapController.php`, its route,
`scripts/install-basemap.sh`, its call in `scripts/deploy.sh`, the basemap
volume mount in `docker-compose.prod.yml`, and the `pmtiles`/
`@protomaps/basemaps` packages in `panel/package.json` are all deleted, not
just unused.

**Why.** Directed change, made with the tradeoff stated up front rather than
discovered later: the self-hosted extract's own entry above documents that
"OpenStreetMap's usage policy doesn't permit embedding it in an application
like this one" — that's still true, and pointing a MapLibre `raster` source
at `tile.openstreetmap.org` is exactly the embedding that policy is about.
The requester was told this directly, including the realistic risk (OSM's
infrastructure can rate-limit or block the deploy VPS's IP under real load),
and chose it anyway because the actual requirement was pixel-identical
OSM Standard styling — the same colours, roads, and labels as osm.org itself
— which no vector re-styling (Carto Positron-alikes, a custom
`@protomaps/basemaps` flavor, or a hand-authored style) can fully reproduce,
and a full self-hosted `osm-carto` raster-rendering stack (`osm2pgsql` +
`renderd`/`mod_tile`, a real OSM data import) was judged too heavy an
addition for this project's "single VPS, boring option" scale to take on
just for basemap cosmetics.

**Consequences.**
- **Real, accepted risk: OSM may rate-limit or block this deployment's IP.**
  Nothing in this codebase works around that risk (no proxy, no caching layer
  in front of the tile requests) — it is a direct, unmitigated dependency on
  a third party's goodwill policy. If OSM tiles stop loading in production
  and the browser console shows 429/403s from `tile.openstreetmap.org`, this
  is why. The fix at that point is a dedicated tile provider (Thunderforest,
  MapTiler, Geoapify, or similar) or reverting to the self-hosted PMTiles
  setup two entries up (still in git history), not trying to route around
  OSM's blocking.
- The viewport-privacy consequence from the Google Maps entry is back,
  pointed at a different third party: OSM's tile servers see every map
  pan/zoom from the panel, the same way Google's did. No employee location
  coordinates are sent to OSM — markers are still drawn client-side from data
  already fetched over `api/`'s own WebSocket/REST endpoints — but the
  viewport itself is visible to OSM's infrastructure.
- No self-hosted basemap file exists any more. `api/storage/app/basemap/
  oman.pmtiles`, if still present on a given machine from the entry above,
  is inert — nothing serves or reads it. It's git-ignored, so no cleanup
  commit is needed to remove it from the repo; it can be deleted by hand
  wherever it's sitting.
- `docs/DESIGN.md` rule 8 is rewritten again to describe the raster OSM
  source and to state explicitly that no filter/opacity/desaturation may be
  layered on top of it — a washed-out or tinted OSM basemap was the specific
  thing rejected in favour of this.
- The self-hosted Oman extract two entries up remains the fallback if this
  policy exception ever becomes a real operational problem — it's a proven,
  working setup (git history, this same session), not a hypothetical.
