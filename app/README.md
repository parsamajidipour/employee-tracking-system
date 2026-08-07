# app/

Flutter, Android only. This phase covers the app shell and device
authentication — see `docs/SPEC.md` section 6 for the full mobile behaviour
and `CLAUDE.md` for the invariants it must respect. No location code,
permissions, or background work exist yet; those come in a later phase, on
top of this shell.

## What's here

- **Login** (`lib/screens/login_screen.dart`) — username/password, posted to
  `POST /api/v1/device/login` along with a stable device identifier generated
  once on first run and persisted forever after (`lib/services/auth_storage.dart`).
- **Home** (`lib/screens/home_screen.dart`) — the employee's entire view:
  today's window (graced times, straight from the server — see
  `lib/models/shift_window.dart`'s docblock on why there's no client-side
  window math), a tracking-state banner, last sync time, and the next
  upcoming window. Falls back to the last cached window (clearly marked
  stale) when `/me/window` is unreachable.
- **Auth state** (`lib/state/auth_controller.dart`) — the one place that
  decides signed-in vs. signed-out, and the one place a 401 on any
  authenticated request gets turned into "sign out, show a deactivation
  notice." `lib/services/api_client.dart` is what actually recognizes that
  401 and reports it upward; nothing retries silently.

Both the token and the device identifier live in `flutter_secure_storage`,
never `SharedPreferences` — see `AuthStorage`'s docblock.

## Running against the api/ dev stack

The base URL is build-time configuration, never a hardcoded host:

```
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:58000
```

`10.0.2.2` is the Android emulator's alias for the host machine's loopback —
`localhost` from inside the emulator means the emulator itself, not your
machine. `58000` matches this repo's `api/.env`'s `API_PORT`; adjust if
yours differs. The default baked into `lib/config.dart` already matches
this, so a plain `flutter run` targeting the emulator works with no flags —
the `--dart-define` above is what you need on a **physical device** on the
same network instead, pointed at the host's real LAN IP.

A device needs an actual account to log in with. The panel's
`AdminUserSeeder` only creates an admin, not an employee with device-login
credentials — create one via the panel's "Add employee" flow (Employees →
Add employee), which is exactly the `username`/`password` this login screen
expects.

## Verifying end to end

1. `docker compose up -d` (from the repo root) so `api/` is reachable at
   the base URL above.
2. `flutter emulators --launch <id>` (see `flutter emulators` for the list),
   then `flutter run`.
3. **Fresh install lands on login** — first launch, no stored token, shows
   the login form.
4. **Login succeeds** — a real employee's username/password (see above)
   signs in and lands on the home screen.
5. **Relaunch skips login** — stop and restart the app (not just hot
   reload); it goes straight to the home screen using the stored token,
   with no login screen flash.
6. **Revoking the device returns the app to login** — in the panel,
   Employees → that employee → Revoke. The app doesn't notice until its
   *next* request (pull-to-refresh on the home screen, or bringing it back
   to the foreground); that request 401s, and the app signs itself out with
   a "this device was deactivated" message on the login screen.

## Known limitations of this phase

- No periodic background resync (SPEC's "every 4 hours") and no push-driven
  resync — both need background infrastructure this run deliberately
  doesn't add. Resync happens on screen load, app foreground, and
  pull-to-refresh only.
- No manual sign-out — the only way back to the login screen is a 401
  (device revoked). Not an oversight: nothing in this phase's scope calls
  for one.
