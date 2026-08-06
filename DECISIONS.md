# Decisions

Lightweight decision log. Newest entries at the bottom. This is not a spec —
`docs/SPEC.md` is the source of truth for behavior; this file is the "why"
behind structural choices that aren't obvious from reading the code.

## Admin UI is Nuxt 3, not Blade or Inertia

**Decision.** The admin panel is a separate Nuxt 3 + Tailwind app (`panel/`),
not server-rendered Blade views or an Inertia-glued SPA inside the Laravel
app. `api/` (the renamed former `panel/`) is Laravel, API only — no views, no
Vite/Tailwind frontend tooling, no `resources/`.

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
`docker-compose.prod.yml`, which is not used in dev.

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
login will fail CORS/CSRF checks. `docker-compose.prod.yml` is for deploying
the panel as a container alongside `api/`; its `panel/Dockerfile` currently
still runs `npm run dev`, not a production build (`nuxt build` +
`.output/`) — that's a known gap, not addressed here.

## No TypeScript in panel/app/ (for now)

**Decision.** `panel/app/` uses plain `<script setup>` and `.js` files, not
`lang="ts"` or `.ts`. This is a workaround, not a style preference — remove
it once the underlying bug is fixed upstream.

**Why.** With `nuxt@3.21.11`, any `.ts` file (or `.vue` with
`lang="ts"`) crashes the dev server: Vite's TS handling looks for
`.nuxt/tsconfig.app.json`, which Nuxt's own root `.nuxt/tsconfig.json`
references but never actually gets generated. This is a real version-skew
bug — `nuxt@3.21.11` depends on `@nuxt/kit@3.21.11` exactly, but the
installed dependency tree also pulls in transitive packages expecting a
newer `@nuxt/kit` (4.x) that writes the split `tsconfig.app.json` /
`tsconfig.node.json` / `tsconfig.shared.json` files; the resolved 3.21.11
`@nuxt/kit` only writes the old single `tsconfig.json`. The result: any file
that needs TS type-stripping fails with `ENOENT` on a file that was never
written.

**Consequences.** If you need TypeScript in the panel, first check whether
a `nuxt`/`@nuxt/kit` patch has fixed this (verify `.nuxt/tsconfig.app.json`
actually gets created after `npx nuxi prepare`). Until then, stick to plain
JS in `panel/app/`.
