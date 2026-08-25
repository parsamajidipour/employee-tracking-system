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

### GET /api/v1/employees/{id}/trail

Reads `location_points` for the given calendar day directly — never
re-resolves the employee's current shift schedule. `shifts` in the response
are groups of points sharing the same `tracking_sessions` row (set once at
ingest time), not a re-derivation from `ShiftWindowResolver`. This means a
day's trail stays readable even if the `employee_shifts` row that governed it
is later changed or deleted.

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
  `409` if the case is not `pending` (already accepted cases cannot be
  silently reassigned).
- `POST /api/v1/cases/{case}/cancel` (`manage-cases`) — `{note?}`.
- `DELETE /api/v1/cases/{case}` (`manage-cases`) — only while still `pending`.
- `GET /api/v1/workload` — every active employee's case summary
  (active/pending/scheduled/overdue/completed counts) plus today's
  travel/inspection/idle-minute split.
- `GET /api/v1/workload/{employee}` — same, for one employee, `?date=`
  optional (defaults to today).

`GET /api/v1/employees/{employee}/trail` (existing) now also returns an
`interruptions` array for the requested day.

## Error responses

## Versioning
