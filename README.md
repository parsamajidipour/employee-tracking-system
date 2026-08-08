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
3. Download the live map's basemap tiles. The live map (`panel/app/pages/map.vue`)
   renders from a self-hosted [Protomaps](https://protomaps.com) PMTiles
   extract instead of any third-party tile server — see `DECISIONS.md`'s
   "Live map tiles" entry for why. The file is a generated artifact, not
   source, and is gitignored — download/build it once per clone:

   ```
   curl -sL -o /tmp/pm.tgz https://github.com/protomaps/go-pmtiles/releases/latest/download/go-pmtiles_1.31.2_Linux_x86_64.tar.gz
   tar -xzf /tmp/pm.tgz -C /tmp
   mkdir -p api/storage/app/basemap
   /tmp/pmtiles extract \
     "https://build.protomaps.com/$(date -u +%Y%m%d).pmtiles" \
     api/storage/app/basemap/oman.pmtiles \
     --bbox=52.0000004,16.4649608,60.0545770,26.7026780 \
     --maxzoom=14
   ```

   This reads only the ~80MB of tiles inside Oman's bounding box out of
   Protomaps' ~120GB daily planet build via HTTP range requests — it does
   not download the whole archive. `go-pmtiles` ships as a prebuilt static
   binary, so no Go toolchain is needed. If `build.protomaps.com`
   doesn't have today's date yet (builds land a few hours into the UTC
   day), try `date -u -d yesterday +%Y%m%d` instead. `api/` is bind-mounted
   into the container (see below), so the file is picked up with no rebuild
   or restart — just refresh the panel.
4. Start the panel on the host:
   ```
   cd panel
   npm install
   npm run dev
   ```
   Serves at http://localhost:3000 by default. **`PANEL_PORT` in the root
   `.env` must match whatever port this actually runs on** — it's not just
   documentation, it's what `docker-compose.yml` uses to build `api/`'s
   `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS`. If you run the
   panel on a different port (e.g. to avoid a clash with another project on
   3000), set `PANEL_PORT` to match *before* `docker compose up`, or via
   `PORT=<port> npm run dev` on the panel side to match an existing
   `PANEL_PORT` — either way the two must agree, or login fails CORS/CSRF
   checks with no more specific error than that. `panel/.env`'s
   `NUXT_PUBLIC_API_BASE` must point at the api's browser-visible URL from
   step 2.

   The live map additionally needs `panel/.env`'s `NUXT_PUBLIC_REVERB_*`
   vars to match `api/.env`'s `REVERB_APP_KEY`/`REVERB_HOST`/`REVERB_PORT`/
   `REVERB_SCHEME` (host-mapped port, same as `API_PORT` above — never the
   container-internal one). `REVERB_APP_SECRET` is not one of them and never
   goes to the browser.

Docker does **not** run the panel in dev — see `DECISIONS.md`.
`docker-compose.prod.unfinished.yml` sketches a containerized panel service
for production, but is not usable yet (`panel/Dockerfile` still runs the dev
server, not a production build) and is not used in dev.

### Code changes and rebuilding

`api/` is bind-mounted into the container (`docker-compose.yml`), so most
edits — controllers, routes, migrations, config, anything under `api/`
except `vendor/` — take effect on the next request with no rebuild, the
same way `panel/`'s `npm run dev` already behaves. `vendor/` is
deliberately *not* live-mounted (an anonymous volume shadows it), so the
container keeps using whatever Composer installed at image-build time
regardless of what's (or isn't) in the host's `api/vendor`.

Rebuild (`docker compose build api`, then `docker compose up -d api`) is
still required for:
- `composer.json` / `composer.lock` changes (new/updated PHP dependencies)
- `api/Dockerfile` changes
- anything that needs a fresh `vendor/` snapshot for another reason

Migrations run automatically on container start (`php artisan migrate
--force`, in the `command:` above) — a new migration just needs the
container restarted (`docker compose restart api`), not rebuilt.

### Trying the login flow

`database/seeders/AdminUserSeeder.php` creates (or repairs) a default admin
account — email `test@example.com`, password `password`, overridable via
`SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD` in `api/.env`. It's idempotent
(`updateOrCreate`), so it always leaves that account active with the
configured password regardless of what state it was in before — run it any
time the dev database gets reset, or whenever login stops working and you
suspect the account itself is the problem rather than the code:

```
docker compose exec api php artisan db:seed
```

Or as part of a full reset:

```
docker compose exec api php artisan migrate:fresh --seed
```

The seeder refuses to run at all under `APP_ENV=production` — these are
documented, publicly-known-default credentials, meant for disposable dev/
staging databases only.

Then log in from the panel at `/login`. A successful login lands on `/map`
(redirected via `/`), which fetches `/api/user` and shows who you're signed
in as in the sidebar — proof the CSRF cookie, session cookie, and CORS/
Sanctum stateful-domain config are all wired correctly end to end.

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
