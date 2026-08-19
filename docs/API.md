# API

## Overview

## Authentication

## Mobile endpoints

### POST /api/v1/track

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

### PUT /api/v1/employees/{id}/schedule

### GET/POST /api/v1/app-releases, DELETE /api/v1/app-releases/{id}

`capability:manage-releases` (admin only). Upload a new `.apk` build
(`version_code`, `version_name`, `release_notes`, `is_mandatory`, `apk` file)
and list or retract prior releases.

## Error responses

## Versioning
