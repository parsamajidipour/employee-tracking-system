# INSPECTION

Workforce location tracking. Supervisors see field employees on a live map
during working hours only — see `docs/SPEC.md` for the full spec and
`CLAUDE.md` for the invariants that hold in every session.

## Layout

```
api/      Laravel 11, API only — mobile API, panel API, WebSocket (Reverb),
          all business logic. Postgres/PostGIS + Redis. Sanctum for the
          panel's SPA (cookie) auth.
panel/    Nuxt 4 + Tailwind admin UI. Authenticates against api/ over
          Sanctum. Runs on the host in dev — see below.
app/      Placeholder. Flutter Android client, built in a later phase.
docs/     SPEC.md, API.md, PRIVACY.md.
```

See `DECISIONS.md` for why the admin UI is Nuxt rather than Blade/Inertia,
and why `panel/` runs on the host instead of in Docker during dev.

## Dev environment

Requires Docker and Docker Compose, plus Node (for the panel) and PHP/Composer
if you want to run `api/` outside Docker too.

1. Copy the env files:
   ```
   cp .env.example .env
   cp api/.env.example api/.env
   cp panel/.env.example panel/.env
   ```
   Generate an app key for `api/.env` (`APP_KEY=`) with:
   ```
   docker compose run --rm api php artisan key:generate
   ```
2. Start Postgres, Redis, and the api:
   ```
   docker compose up
   ```
   This brings up Postgres (with PostGIS enabled), Redis, and `api/`, then
   runs migrations and serves the API at http://localhost:8000 (port
   configurable via `API_PORT` in the root `.env`).
3. Start the panel on the host:
   ```
   cd panel
   npm install
   npm run dev
   ```
   Serves at http://localhost:3000 by default (`PANEL_PORT` in the root
   `.env` controls what `api/`'s CORS/Sanctum config expects — keep the two
   in sync if you change the port). `panel/.env`'s `NUXT_PUBLIC_API_BASE`
   must point at the api's browser-visible URL from step 2.

Docker does **not** run the panel in dev — see `DECISIONS.md`.
`docker-compose.prod.unfinished.yml` sketches a containerized panel service
for production, but is not usable yet (`panel/Dockerfile` still runs the dev
server, not a production build) and is not used in dev.

### Trying the login flow

Create a test user, then log in from the panel at `/login`:

```
docker compose exec api php artisan tinker --execute="
  \App\Models\User::firstOrCreate(['email' => 'test@example.com'], ['name' => 'Test User', 'password' => bcrypt('password')]);
"
```

A successful login lands on `/`, which fetches `/api/user` and shows who
you're signed in as — proof the CSRF cookie, session cookie, and CORS/Sanctum
stateful-domain config are all wired correctly end to end.

## Running tests

Tests run against a real Postgres+PostGIS database — `api_testing`, a second
database on the same `postgres` container as the dev database, created
automatically (with the PostGIS extension enabled) by
`docker/postgres/init-test-db.sh` the first time the `postgres` container
initializes its data volume. This is configured in `api/phpunit.xml`
(`DB_CONNECTION=pgsql`, `DB_DATABASE=api_testing`); host, port, and
credentials come from `api/.env`, same as the dev database.

The Docker stack must be up, since the tests hit `postgres` for real:

```
docker compose up -d postgres
cd api
php artisan test
```

If `api_testing` doesn't exist yet (e.g. the postgres volume already existed
before this was added), recreate the volume so the init script runs:

```
docker compose down -v
docker compose up -d postgres
```

The panel has no test suite yet.
