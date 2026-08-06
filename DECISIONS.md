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

## Live map tiles: OpenStreetMap's standard raster servers, for now

**Decision.** The live map (`panel/app/pages/map.vue`) renders with
MapLibre GL JS against a plain raster source pointed at OpenStreetMap's
standard tile servers (`https://tile.openstreetmap.org/{z}/{x}/{y}.png`).
No API key, no account, no billing.

**Why.** Every alternative that looks more "production" (MapTiler, Stadia
Maps, Mapbox) requires a signup and an API key even on their free tiers —
which means either committing a key to the repo (never happening) or adding
a whole new secret-provisioning step to `README.md`'s dev setup before a
single marker renders. OSM's tile servers need neither, which matches this
phase: `CLAUDE.md` says reach for the boring option, and right now "does a
marker show up on a map" doesn't justify a new account anywhere.

**Consequences — read before shipping this past a demo.** Every tile
request is a direct browser request to `tile.openstreetmap.org`, carrying
the supervisor's viewport (which map area, at what zoom, how often) to a
third party outside this system. That's a smaller leak than a location
point ever reaching them — OSM never sees an employee's coordinates, only
which map tiles a supervisor's browser happens to be looking at — but it's
still a real one, and it sits oddly next to a product whose entire premise
is *not* leaking location data to parties who don't need it. OSM's tile
usage policy also just plainly disallows heavy production traffic without
self-hosting.

Two credible next steps once this matters, in ascending effort: (1) a free
provider that still needs a key (MapTiler's free tier is generous at this
project's scale) — trades "no signup" for "no third-party viewport
telemetry on every request being someone else's business model," which
isn't actually true either since the provider still sees requests, just
under a commercial ToS instead of OSM's community one; (2) self-host tiles
(a `tileserver-gl` container serving a pre-built extract of Oman from
OSM data) — the only option that removes the third party entirely, at the
cost of a new service and a data-refresh story. Revisit before a real
deploy, not before.
