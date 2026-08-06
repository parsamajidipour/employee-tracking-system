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

## Live map tiles: self-hosted PMTiles extract, served from `api/`

**Decision.** The live map (`panel/app/pages/map.vue`) renders with
MapLibre GL JS against a single PMTiles archive (`api/storage/app/basemap/
oman.pmtiles`, a Protomaps basemap extract clipped to Oman's bounding box,
zoom 0–14), served by `api/`'s own `GET /api/basemap/oman.pmtiles` route
(`App\Http\Controllers\BasemapController`) and read client-side with the
`pmtiles` package's `Protocol` handler plus `@protomaps/basemaps`' vector
style layers. No new container: `response()->file()` on a `BinaryFileResponse`
already gets `Range`-request handling for free from Symfony, which is all
the PMTiles HTTP-range-read model needs.

This replaces the previous entry below (OSM's standard raster tile
servers), which is superseded, not just amended — the "viewport leaks to a
third party" consequence that entry accepted is what this decision removes.

**Why.** `tile.openstreetmap.org`'s usage policy doesn't permit embedding
it in an application like this one, and OSM's own infrastructure will
rate-limit or block it under any real load — that was flagged as a known
gap in the previous entry, and it's not something to leave sitting once
there's a next task touching this file. PMTiles makes self-hosting cheap
enough to do immediately instead of deferring it: it's one file, range-read
over plain HTTP, no tile-serving process, no cache layer, no per-tile
routing — the file itself *is* the server-side state. That fits `CLAUDE.md`
better than the `tileserver-gl`-container alternative the old entry
sketched: one static file beats a new long-running service at this
project's scale.

**How the extract was built.** Protomaps publishes a daily-updated
planet-wide basemap PMTiles archive at `https://build.protomaps.com/
<YYYYMMDD>.pmtiles` (~120GB). PMTiles' layout supports extracting a
bounding box from a *remote* archive via HTTP range requests alone —
nothing close to 120GB is downloaded. The `go-pmtiles` CLI's `extract`
subcommand did this for Oman's bbox in about 80MB / a few dozen HTTP
requests total. See `README.md`'s dev-setup step for the exact command —
it is not committed (see below) and needs to be re-run once per clone/reset.

**Consequences.**
- `api/storage/app/basemap/oman.pmtiles` is a generated artifact, not
  source — gitignored (already covered by the existing blanket
  `api/storage/app/*` / `api/storage/app/.gitignore` rules, nothing new
  needed there), and regenerated locally by the README's download step, not
  fetched from any application-controlled URL at runtime.
- `GET /api/basemap/oman.pmtiles` is intentionally unauthenticated — it's
  OSM-derived basemap geometry, the same data anyone could get from OSM
  directly, not employee location data. Nothing in `CLAUDE.md`'s access
  rules applies to it.
- `api/config/cors.php`'s `exposed_headers` now includes `Content-Range`,
  `Content-Length`, and `Accept-Ranges` — the pmtiles client reads these off
  the `fetch()` response to do its range reads, and cross-origin `fetch()`
  hides all response headers from JS unless a CORS response explicitly
  exposes them.
- No `glyphs`/`sprite` URL is set in the MapLibre style. Text labels still
  render — MapLibre falls back to local system fonts when no glyphs
  endpoint is configured — but POI icons don't (no local fallback exists
  for sprite images; console shows "could not be loaded" warnings for
  them). Leaving both unset is deliberate either way: this is still the
  no-design-pass phase, and standing up a font/sprite service (third-party
  or self-hosted) to draw icons would reintroduce exactly the kind of
  external request this change exists to remove, for a cosmetic gain no one
  asked for yet.
- The extract is zoom 0–14 and was built from whatever day's build was
  current when generated — Protomaps' daily builds aren't meant to be
  hotlinked or diffed against by date, so there's no "refresh" story here
  beyond "re-run the extract command against a current daily build" if the
  underlying OSM data ever needs updating. Not automated; revisit if stale
  data becomes an actual complaint.

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
