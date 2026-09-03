# API

## Overview

## Authentication

## Mobile endpoints

### POST /api/v1/track

### POST /api/v1/track/ping

Live-map-only position ping, gated by the same shift window as `/track` but
never persisted to `location_points` — no trail row, no distance, not part of
history. `{accepted: bool}`.

### GET /api/v1/me/window

### GET /api/v1/me/schedule-changes

### GET /api/v1/me/today

### GET /api/v1/app/latest-version

Public, unauthenticated. Returns the highest `version_code` release for the
Android APK update-check flow: `version_code`, `version_name`, `release_notes`,
`is_mandatory`, `download_url`. 404 if no release has been uploaded yet.

### GET /api/app-releases/{id}/download

Public, unauthenticated. Streams the `.apk` for that release. 503 if the file
is missing on disk.

## Panel endpoints

### GET /api/v1/positions

### WebSocket channel `positions`

### WebSocket channel `cases`

Private channel, authorised by `capability:view-cases`. Carries
`case.changed` (`App\Events\CaseChanged`, `ShouldBroadcastNow`) with
`{action, case_id, case}` — `action` is one of `created`, `assigned`,
`accepted`, `rejected`, `started`, `completed`, `cancelled`, `deleted`, and
`case` is a full `CaseResource` snapshot (`null` for `deleted`). Every write
path in `CaseLifecycleService` plus `CaseController::destroy` emits it, so the
panel's case list, case detail and workload pages stay current without
polling or a manual refresh.

### GET /api/v1/notifications

Any authenticated user. `{data: [...], unread_count}` — the 50 most recent
database notifications for the caller, each `{id, type, message, case_id,
reference_no, read_at, created_at}`. `type` is the broadcast type
(`case.created`, `case.assigned`, `case.status-changed`, `schedule.changed`,
`device.revoked`, `app-release.published`), and `message` is a
ready-to-display sentence built server-side so the panel and the phone show
identical wording.

`POST /api/v1/notifications/{id}/read` and
`POST /api/v1/notifications/read-all` mark them read; both return `204`. The
same notifications also arrive live on the existing per-user private channel
`App.Models.User.{id}`.

### GET /api/v1/employees/{id}/trail

Reads `location_points` for the given calendar day directly — never
re-resolves the employee's current shift schedule. `shifts` in the response
are groups of points sharing the same `tracking_sessions` row (set once at
ingest time), not a re-derivation from `ShiftWindowResolver`. This means a
day's trail stays readable even if the `employee_shifts` row that governed it
is later changed or deleted.

### GET/POST /api/v1/employees/{id}/leaves, DELETE /api/v1/employee-leaves/{id}

`capability:manage-schedules`. A leave is one continuous range
(`starts_at`, `ends_at`, optional `note`), not a per-day row. Inside it the
employee's shift never opens: no point is persisted, no live ping is published,
nothing about them reaches the map. `starts_at` must not be in the past and the
range may not overlap an existing leave for that employee.

`GET` returns the employee's leaves newest first, paginated (`page`,
`per_page`, default 15) with the usual `data` / `meta` envelope. `DELETE`
cancels a leave — a soft delete, so the row survives for the audit trail — and
both writes append a `schedule_change_log` row.

### PUT /api/v1/employees/{id}/schedule

### GET/POST /api/v1/app-releases, DELETE /api/v1/app-releases/{id}

`capability:manage-releases` (admin only). Upload a new `.apk` build
(`version_code`, `version_name`, `release_notes`, `is_mandatory`, `apk` file)
and list or retract prior releases.

## Case / field-operations endpoints (Phase 2)

### Mobile (self-service, no capability required beyond ownership)

- `GET /api/v1/me/cases` — cases assigned to the current employee, open ones
  first. `?status=` filters.
- `GET /api/v1/me/cases/unseen-count` — `{pending, unread_notifications}`.
- `GET /api/v1/me/cases/{case}` — 403 unless assigned to the caller.
- `POST /api/v1/me/cases/{case}/accept` — `{planned_at}`. `pending → accepted`.
- `POST /api/v1/me/cases/{case}/reject` — `{note?}`. `pending → rejected`.
- `POST /api/v1/me/cases/{case}/start` — `accepted → in_progress`.
- `POST /api/v1/me/cases/{case}/complete` — `{note?}`. `in_progress → completed`.
- `POST /api/v1/me/cases/{case}/photos` — multipart `{photo, lat, lng,
  accuracy_m?, captured_at}`. Never rejected for being far from the case;
  `is_gps_verified` is a flag, not a gate — see `DECISIONS.md`.
- `GET /api/v1/case-photos/{casePhoto}` — streams the file. Allowed for the
  photo's own employee or anyone with `view-cases`.
- `POST /api/v1/tracking-interruptions/start` — `{reason, at}`. Silently
  ignored (`accepted: false`) if no shift window resolves at `at`.
- `POST /api/v1/tracking-interruptions/stop` — `{at}`. Closes any open
  interruption for the caller.

Invalid status transitions return `409`, not `422` — the request shape was
valid, the case's current state just doesn't allow it.

### Panel (`capability:view-cases` / `capability:manage-cases`)

- `GET /api/v1/cases` — paginated, filterable by `status` and `assigned_to`.
- `GET /api/v1/cases/{case}` — includes `status_events` and `photos`.
- `GET /api/v1/cases/{case}/nearest-surveyors` — ranked by live-position
  distance (PostGIS, not routed — see `DECISIONS.md`), tie-broken by open
  case count. Only employees currently inside a resolved shift window and
  present in the live-position cache are returned.
- `POST /api/v1/cases` (`manage-cases`) — optional `assigned_to` assigns
  immediately on creation. Every active employee is notified the case exists
  (database + Reverb broadcast, `CaseCreatedNotification`, event
  `case.created`) regardless of whether it was assigned at creation.
- `POST /api/v1/cases/{case}/assign` (`manage-cases`) — `{employee_id}`.
  `422` if the employee is deactivated, soft-deleted, or not an employee —
  an inactive account is never assignable, and the message says so. `409` if
  the case is neither `pending` nor `rejected` (an accepted case cannot be
  silently reassigned; a rejected one is explicitly reassignable, which is
  what the panel's "Reassign" action does).
- `POST /api/v1/cases/{case}/cancel` (`manage-cases`) — `{note?}`.
- `DELETE /api/v1/cases/{case}` (`manage-cases`) — only while still `pending`.
- `DELETE /api/v1/employees/{employee}` — `409` if the employee still has an
  open (`pending`/`accepted`/`in_progress`) case assigned; those must be
  reassigned or cancelled first rather than left pointing at a deleted user.
- `DELETE /api/v1/employees/{employee}/device` — `409` with a message when
  there is no active device, instead of the previous silent `204` no-op.
- `GET /api/v1/workload` — every active employee's case summary
  (active/pending/scheduled/overdue/completed counts) plus today's
  travel/inspection/idle-minute split.
- `GET /api/v1/workload/{employee}` — same, for one employee, `?date=`
  optional (defaults to today).

`GET /api/v1/employees/{employee}/trail` (existing) now also returns an
`interruptions` array for the requested day.

## Error responses

## Versioning
