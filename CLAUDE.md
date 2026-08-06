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
- No window arithmetic in controllers, Eloquent scopes, or Blade/Vue.

## Conventions

**Time.** Store UTC. Resolve windows in the employee's team timezone
(`Asia/Muscat`). Never compare a raw timestamp to a raw `start_time` without going
through the resolver.

**Tests.** `ShiftWindowResolver` and `TrackingGate` are the two things that must
have thorough tests, including: a point recorded in-window but delivered hours
late, a point recorded out of window, an employee with no schedule at all, a
schedule change taking effect mid-day, DST-free but timezone-offset boundaries,
and a point with a client clock set forward.

**Migrations.** Additive. Never edit a shipped migration.

**Secrets.** Never commit `.env`, keys, tokens, or real coordinates. Seed data uses
synthetic coordinates, not real Oman locations.

**Android.** Foreground service with a persistent notification whenever tracking is
active. Acquisition is `distanceFilter` 30m plus a 60s heartbeat, with reduced
frequency when stationary — never a fixed high-frequency interval. Points queue in
local SQLite and flush every ~30s. The service starts at window open and stops at
window close.

## Definition of done

A change touching tracking, scheduling, or access is not done until:

- tests cover the boundary cases above
- the audit or change log row is written where required
- the employee-facing view of the same data still matches what the panel shows

## Ask, do not assume

If a task is ambiguous about *who can see what* or *when data is collected*, ask.
Those two questions are where this project can be got wrong in a way that is
expensive to unwind. Everything else, use judgement and keep moving.
