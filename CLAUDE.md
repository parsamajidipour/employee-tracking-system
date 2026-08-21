# CLAUDE.md

Read `docs/SPEC.md` before starting work in a new area. This file holds the rules
that must hold in every session; SPEC.md holds what we are building.

## What this is

A workforce location tracking system. Supervisors see field employees on a live map
**during working hours only**. Outside a working-hours window, no location data
about an employee exists anywhere in the system.

That second sentence is the product. Features that weaken it are not improvements,
they are regressions, even if a stakeholder asked for them.

## Repo

```
panel/    Laravel monolith — mobile API, admin UI, WebSocket, all business logic
app/      Flutter, Android only
docs/     SPEC.md, PRIVACY.md, API.md
```

Scale: 50 employees, ceiling 150. Single VPS. Postgres + PostGIS with native
monthly partitioning, Redis, Laravel Reverb. No Timescale, no Kubernetes, no
microservices. Reach for the boring option.

## Invariants

Never violate these. If a task appears to require violating one, stop and say so
instead of finding a way around it.

1. A location point is persisted only after passing the shift gate.
2. The gate evaluates `recorded_at`, never `received_at`.
3. If no shift window resolves for an employee at that instant, the point is
   rejected. Default is deny, not allow.
4. Rejected points increment a counter and are discarded. Never written to
   storage, never soft-deleted, never flagged for later review.
5. No API endpoint returns location data recorded outside a shift window.
6. `effective_from` on a schedule change cannot be in the past.
7. Every trail (historical path) read writes an `access_audit_log` row.
8. The window shown in the app and the window enforced by the server come from the
   same resolver. There is never a second implementation of window logic.
9. `schedule_change_log` and `access_audit_log` are append-only. No application
   code path updates or deletes rows in them.

## Where logic lives

- Window resolution: one class, `App\Services\ShiftWindowResolver`. Everything
  else calls it. Controllers, jobs, commands, the app-facing endpoint, all of it.
- The gate: `App\Services\TrackingGate`, which calls the resolver. The ingest
  controller does not decide anything itself.
- History reads (`EmployeeHistoryController::trail`) never call the resolver.
  Whether a point exists in `location_points` was already decided once, at
  ingest, by the gate above — a history read just shows what's there, grouped
  by `tracking_sessions` (set at ingest, never recomputed). Re-resolving the
  employee's *current* schedule to read back the past was tried and reverted
  in this session: a later shift edit or deletion made already-recorded days
  unreadable even though the points were still safely stored.
- No window arithmetic in controllers, Eloquent scopes, or Blade/Vue.

## Conventions

**Comments.** Code carries no comments. No `//`, no `/* */`, no prose docblocks, in
any language. The only thing allowed inside a docblock is a machine-readable type
annotation that a tool actually consumes — `@param`, `@return`, `@var`, `@use`,
`@extends`, `@mixin`, `@template`, `@implements`, `@throws`. Nothing else.

If something needs explaining, it goes in a markdown file, not next to the code:
architecture and rationale in `DECISIONS.md`, product behaviour in `docs/SPEC.md`,
rules that must hold every session in this file. A rule that gets agreed on during
a session gets written to the relevant `.md` in that same session, not left in a
comment.

Names carry the explanation instead. If a block needs a comment to be readable,
extract it into a named method.

**Time.** Store UTC. Resolve windows in `config('tracking.timezone')`
(`Asia/Muscat`). One timezone for the whole deployment — it is not per user and not
per shift template. Never compare a raw timestamp to a raw `start_time` without
going through the resolver.

**No teams.** There is no `teams` table and no `team_id` anywhere. This is one
organisation. Shift templates are organisation-wide reusable definitions (name,
days, times, grace), but they are opt-in, never ambient: a template governs an
employee only through an explicit `employee_shifts` row. Creating a shift
template must never, by itself, start tracking anyone. The resolver has no
"default template" level — if an employee has no active `employee_shifts` row
covering the instant (and no exception), the answer is null, full stop. This
was tried the other way (an implicit organisation-wide fallback template) and
reverted in this session specifically because it silently started tracking
employees nobody had assigned a shift to.

**Laravel style.** Stay on the framework's own rails. Controllers are thin: they
validate through a FormRequest, call a service, and return a Resource. Business
logic lives in `App\Services`, shared model behaviour in `App\Models\Concerns`,
fixed value sets in `App\Enums`, response shapes in `App\Http\Resources`. No route
closures. Third-party packages are limited to `laravel/*` and `spatie/*` — anything
else needs a `DECISIONS.md` entry justifying it.

**Network exposure.** The whole stack runs in `docker-compose.yml` — api, panel,
postgres, redis. Postgres and Redis publish on `127.0.0.1` only and must stay
that way; only the API, Reverb and the panel are reachable from the network.

There is exactly one place the machine's network address is written down:
`LAN_HOST` in the root `.env`. It feeds the API's CORS and Sanctum allow-lists
and the URL baked into the APK. The panel never needs it — it derives the API
origin from the host the browser loaded it from, so one panel build works from
localhost and from a phone alike. Do not add a second source for this address.

`APP_DEBUG` is false. A stack trace is a map of the application, and this one is
reachable from every device on the office Wi-Fi.

**Android packaging.** One universal APK, `arm64-v8a` and `armeabi-v7a` only, so a
single file installs on every real phone. `x86_64` is deliberately left out — no
real phone ships with it, only emulators and a handful of Chromebooks do, and it
was the single largest ABI in the APK (~18MB of ~53MB before it was dropped).
`abiFilters` in `android/app/build.gradle` documents this but doesn't actually
enforce it — Flutter's own gradle plugin packages its engine/app `.so` files
through its own logic, ignoring `abiFilters` for those. The real mechanism is the
`--target-platform android-arm,android-arm64` flag, which must be passed on every
release build (already wired into `scripts/build-apk.sh`) — omit it and `x86_64`
silently comes back. `minSdk` is whatever the plugin set actually requires and is
declared explicitly in `build.gradle` rather than left to manifest merging —
check it against the built APK's `sdkVersion`, they must agree. Release builds
are signed with the real key and minified; the keystore and `key.properties` are
git-ignored and must be backed up, since losing them blocks every future upgrade.
Cleartext HTTP is permitted only for the specific hosts in
`network_security_config.xml`, never globally.

**Ports.** Every host port published by `docker-compose.yml` stays below 49152.
Windows reserves ranges inside 49152-65535 for Hyper-V/WSL2, and a container
publishing into a reserved range fails to start with a `/forwards/expose` 500.
See `information.txt`.

**Tests.** `ShiftWindowResolver` and `TrackingGate` are the two things that must
have thorough tests, including: a point recorded in-window but delivered hours
late, a point recorded out of window, an employee with no schedule at all, a
schedule change taking effect mid-day, DST-free but timezone-offset boundaries,
and a point with a client clock set forward.

Tests own `api_testing` and never touch the `api` database. Nothing in
`docker-compose.yml` may put `DB_CONNECTION` or `DB_DATABASE` into the api
container's environment — not through `environment:`, not through `env_file:`.
A real process environment variable beats `phpunit.xml`'s `<env>` (even with
`force="true"`), so setting it there silently points `RefreshDatabase` at the
development database and wipes it on every run. The container reads `api/.env`
itself through the bind mount; only the values that must differ inside the
container (`DB_HOST`, `DB_PORT`, credentials, Redis, CORS) belong in compose.

Sanity check after touching any of this: seed, run the suite, and confirm `api`
still has rows while `api_testing` has the migrations.

**Migrations.** The project now has a live production deployment (the VPS at
164.90.163.27, deployed via `.github/workflows/deploy.yml` on every push to `main`)
with real employee and schedule data, so the rule has flipped: migrations are
additive-only from here on. Never edit a `create_*` migration that may already
have run somewhere — `php artisan migrate` only runs a migration once per
database, keyed by filename, so editing one after it has already run against a
database (production, or any teammate's already-migrated local db) is a silent
no-op there while `migrate:fresh` locally hides the problem completely. Add a new
migration instead. If it touches a table that might already exist in that shape
in production, make it idempotent (`Schema::hasColumn`, a guarded raw
`information_schema`/`pg_constraint` check, `CREATE INDEX IF NOT EXISTS`) so it is
safe to run against both a freshly created table and one that already has the
column. See `2026_08_18_000001_add_effective_dates_to_employee_shifts_table.php`
for the pattern — it backfills existing rows rather than assuming the table is
empty.

Every migration declares its own indexes. Index what is actually queried: the
composite the read path filters and sorts on, a partial unique index where "at most
one live row per owner" is a real rule, GiST on geography columns. Do not index a
column just because it is a foreign key.

**Seeders.** `migrate:fresh --seed` must produce a database where every table has
rows and every enum case is represented — all four `shift_exceptions` types, every
`UserRole`, an inactive employee, an open session and a closed one. Seeders are
idempotent (`updateOrCreate`/`firstOrCreate`) and refuse to run in production.
Location points are seeded only inside a resolved window, through the resolver, so
seed data can never contradict invariant 1.

**Local access notes.** `information.txt` is generated by
`scripts/write-information.sh`, never hand-written. It reads the addresses,
ports and seeded credentials out of the real config files, so a hand-typed
default cannot drift away from what the seeders actually create. After changing
`.env`, `api/.env`, or a seeder, re-run the script. Anything documented in there
must be verified against a live request before it is claimed to work.

**Secrets.** Never commit `.env`, keys, tokens, or real coordinates. Seed data uses
synthetic coordinates, not real Oman locations. Local credentials and addresses
live in `information.txt`, which is git-ignored.

**Security.** Deny by default and assume the caller is hostile. Every write goes
through a FormRequest — no `$request->all()` into a model. Every authenticated
route sits behind a capability, never a role comparison. Anything that accepts a
password from an anonymous caller is rate-limited per identifier plus IP.
Deactivating an account or changing its password revokes that account's tokens in
the same request, so an issued token cannot outlive the change that should have
ended it.

**Android.** Foreground service with a persistent notification whenever tracking is
active. `LocationAcquisitionService` polls GPS every 10s. Every poll fires a live
ping (`POST /api/v1/track/ping` → `TrackingGate::ping()`) that goes through the
same `ShiftWindowResolver` gate and updates the live map, but is never written to
`location_points` — no trail row, no distance, nothing an audit or a trail read
ever sees. Separately, a point is recorded into the local SQLite queue (and from
there uploaded and persisted to `location_points`, feeding the trail and its
distance) once 15 seconds have passed since the last recorded one, **and** the
device has moved at least 20m from that last recorded point — either condition
unmet and nothing is recorded, so a stationary employee produces no new trail
points at all. So the live map stays near-real-time off the ping, and route
playback/distance stay close to real movement without recording redundant
points while stationary. Points queue in local SQLite and flush immediately
after each point is recorded, with the foreground task's 5s repeat cycle as a
retry backstop. The service starts at window open and stops at window close.

**Design.** One design system, defined in `docs/DESIGN.md` — currently the panel
only (`app/lib/theme/app_theme.dart`, the Flutter side, is behind and flagged as
such at the top of that doc; don't treat it as current). Colours, radii, spacing
and elevation come from tokens in that document — no ad-hoc hex values in a
component.

Hybrid, not uniformly soft or calm: light surfaces for tables/forms/cards, dark
surfaces reserved for the live map, histories map, and nav rail — see
`docs/DESIGN.md` §1 for the reasoning. Compact density. Indigo accent. Never pure
black on pure white even on the dark surfaces (`#0B0B10`, not `#000`).

The app must stay smooth on weak hardware. No blur/backdrop filters, no shadows
inside list items that scroll, no animation that runs when nothing changed —
the one deliberate exception is the live map's online-marker pulse, which encodes
a real current state. Transitions are 120-260ms on standard easing, and they exist
to explain a change of state, not to decorate. Anything expensive is opt-in behind
a device check.

Accessibility and reach come before visual ambition: tap targets at least 44dp on
touch (`(pointer: coarse)`, smaller compact sizing is desktop/mouse-only), text
contrast at least 4.5:1, layouts that survive 320dp width and 200% text scale.

## Definition of done

A change touching tracking, scheduling, or access is not done until:

- tests cover the boundary cases above
- the audit or change log row is written where required
- the employee-facing view of the same data still matches what the panel shows

## Ask, do not assume

If a task is ambiguous about *who can see what* or *when data is collected*, ask.
Those two questions are where this project can be got wrong in a way that is
expensive to unwind. Everything else, use judgement and keep moving.
