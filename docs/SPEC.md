# Workforce location tracking — specification

Draft v0.1. This document is the source of truth. Code follows this; if code and
this document disagree, this document wins until it is explicitly amended.

## 1. Purpose

Supervisors need a live map of field employees during working hours in order to
coordinate work. Outside working hours, no location data about an employee exists
anywhere in the system.

The second sentence is a hard product requirement, not a nice-to-have. Most design
decisions below exist to make it structurally true rather than a matter of policy.

## 2. Repository layout

```
tracking/
├─ api/        Laravel, API only: mobile API + panel API + WebSocket
├─ panel/      Nuxt 4 admin UI, authenticates against api/ via Sanctum SPA
├─ app/        Flutter, Android only in phase 1
├─ docs/       SPEC.md, PRIVACY.md, API.md
└─ CLAUDE.md   invariants that must hold in every session
```

The admin UI was originally planned as a Blade/Inertia part of the Laravel
monolith. It is Nuxt 4 instead, talking to `api/` over Sanctum's SPA
(cookie-based) authentication. See `DECISIONS.md` for why.

## 3. Invariants

These must never be violated. They belong in `CLAUDE.md` verbatim.

1. A location point is persisted only after passing the shift gate.
2. The gate evaluates `recorded_at`, never `received_at`.
3. If no shift window resolves for an employee at that instant, the point is
   rejected. Default is deny, not allow.
4. Rejected points increment a counter. They are never written to storage, not
   even flagged or soft-deleted.
5. No API endpoint returns location data recorded outside a shift window.
6. `effective_from` on a schedule change cannot be in the past.
7. Every trail (historical path) read writes an audit log row.
8. The shift window shown in the app and the window enforced by the server come
   from the same resolver. There is no second implementation.

## 4. Domain model

### Identity
- `users` — id, name, phone, role, team_id, is_active
- `teams` — id, name, timezone

Roles: `admin`, `hr`, `supervisor`, `employee`.

### Schedule
- `shift_templates` — id, team_id, name, timezone, days_of_week, start_time,
  end_time, grace_before_min, grace_after_min, max_daily_minutes
- `employee_shifts` — id, employee_id, template_id, effective_from, effective_to
- `shift_exceptions` — id, employee_id, date, type, start_at, end_at, note
  - type: `leave` | `holiday` | `overtime` | `early_end`

Resolution order for a given (employee, instant):
`shift_exceptions` → `employee_shifts` → team `shift_templates` → deny.

### Tracking
- `tracking_sessions` — id, employee_id, started_at, ended_at, end_reason
  - end_reason: `window_closed` | `manual_pause` | `permission_revoked` | `stale`
- `location_points` — id, session_id, employee_id, location (geography Point 4326),
  accuracy_m, speed_mps, heading_deg, battery_pct, is_mocked, recorded_at,
  received_at
  - native Postgres monthly partitioning on `recorded_at`, so expiry is a
    `DROP PARTITION` rather than a `DELETE`. No Timescale at this scale.
  - index on `(employee_id, recorded_at)`

### Accountability
- `schedule_change_log` — append-only: actor_id, target_employee_id, before, after,
  effective_from, reason, created_at
- `access_audit_log` — append-only: actor_id, action, target_employee_id, ip,
  created_at

Neither table has an update or delete path in application code.

## 5. API contract

Version prefix `/api/v1`. Mobile authenticates with a device-bound token.

### Mobile
- `POST /track` — batch of points.
  Request: `{ points: [{ lat, lng, accuracy_m, speed_mps, heading_deg,
  battery_pct, is_mocked, recorded_at }] }`
  Response: `202 { accepted: n, rejected: n, server_time }`
  Rejection is not an error. The app deletes the whole batch from its queue on 202.
- `GET /me/window?date=` — the window as the server will enforce it, plus
  `schedule_version` and `changed_at`.
- `GET /me/schedule-changes` — this employee's own rows from `schedule_change_log`.
- `GET /me/today` — the employee's own session and points for today.

### Panel
- `GET /positions` — snapshot of last known position per employee currently in
  window. Used for initial map load.
- WebSocket channel `positions` — deltas only.
- `GET /employees/{id}/trail?date=` — permission-gated, audited.
- `PUT /employees/{id}/schedule` — writes `schedule_change_log`, pushes to device.

## 6. Mobile behaviour (Android)

- Foreground service with a persistent, non-dismissable notification while tracking.
- Acquisition: `distanceFilter` 30m plus a 60s heartbeat. Reduce frequency when
  stationary. Not a fixed high-frequency interval.
- Points queue in local SQLite. Flush every ~30s when connected.
- Points older than 48h are discarded by the client without sending.
- Schedule resync on: push notification, app foreground, before session start, and
  every 4 hours.
- Home screen always shows: today's window, tracking state, last sync time.
- A schedule change produces a push notification and an in-app banner showing the
  previous and new window, who changed it, and when.
- Pause button: fixed maximum duration, logged, visible to the supervisor.
- The employee can see their own position on the same map the supervisor sees.
- Prompt for battery optimisation exemption on first run, with an explanation.

## 7. Retention

- `location_points`: 60 days, then hard delete.
- Daily aggregates (hours in window, distance, sites visited) retained 2 years.
- Audit and change logs: retained for the life of the system.

## 8. Phase 1 scope

In:
- Android app, foreground service, offline queue
- Shift gate with templates, per-employee overrides, exceptions
- Live map with markers, staleness colouring, marker detail panel
- Trail view behind a separate permission
- Schedule editing with change log and push notification
- Employee self-view of window, change history, and own position

Out:
- iOS
- Geofences and site check-in
- Task or work-order assignment
- Payroll or timesheet integration
- Reporting beyond a basic daily summary

## 9. Open questions

1. ~~Expected number of tracked employees.~~ Resolved: 50 at launch, plan for 150.
   Single VPS, 2–4 vCPU / 4–8 GB RAM. PostGIS yes, Timescale no.
2. Who is allowed to change a schedule, and does it require a second approval.
3. Maximum permitted daily window length, per Oman labour law.
4. Are the devices company-owned or personal. This changes the consent process.
5. Is Arabic UI required in phase 1.
6. Who signs off the privacy notice given to employees.
