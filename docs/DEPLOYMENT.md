# Deployment

The repository ships **production-ready** out of the box. The `Dockerfile`
is a multi-stage build (composer + php-fpm-alpine) that runs as `www-data`
under `tini`, with OPcache, `phpredis`, `pdo_pgsql`, and `bcmath` baked in.

## Compose files

Two explicitly-named files, no `docker-compose.override.yml` auto-merge
magic:

| File | Role |
|---|---|
| `docker-compose.prod.yml` | Production stack — built image, no source bind-mount, no seeding, OPcache cold-cached |
| `docker-compose.dev.yml` | Local dev overlay — bind-mounts source, enables seeding, debug logging, OPcache mtime checks |

The Makefile composes them with explicit `-f` flags:

```bash
make up        # → docker compose -f docker-compose.prod.yml -f docker-compose.dev.yml up -d
make up-prod   # → docker compose -f docker-compose.prod.yml up -d
```

## Service topology

| Service | Role |
|---|---|
| `app` (php-fpm) | Application server, runs the Laravel kernel |
| `nginx` | Public HTTP front for `app` |
| `queue` | `php artisan queue:work` against Redis |
| `scheduler` | `php artisan schedule:work` for `ExpireReservationsJob` |
| `postgres` | PostgreSQL 17 with the project schema |
| `redis` | Redis 7 — cache, sessions, queue, idempotency |

## Production deploy

```bash
cp .env.production.example .env.production
# Replace placeholder secrets via your secret manager (AWS SM, Vault, etc.)
docker compose -f docker-compose.prod.yml up -d
# or: make up-prod
```

The `entrypoint.sh` waits for Postgres, runs `migrate --force` (gated by
`RUN_MIGRATIONS=true`), warms config/route/view caches when
`OPTIMIZE_ON_BOOT=true`, and only the `app` role runs first-boot tasks so
multiple replicas of `queue`/`scheduler` cannot race on cache warming.

## What a real production rollout would add

- Push the built image to a registry (GHCR / ECR) and deploy via your
  orchestrator (Kubernetes, ECS, Cloud Run, Swarm).
- Pull `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD` from a secret store rather
  than `.env.production`.
- Front nginx with a TLS terminator (ALB, Traefik, Cloudflare) and remove
  the `127.0.0.1:` port pin from `postgres`/`redis` if those services live
  outside the cluster.

## CI

`.github/workflows/ci.yml` runs on every push:
**Pint → Pest (Unit + Feature + Concurrency × 10) → Docker image build.**
The concurrency suite runs ten consecutive times to catch flakiness — a
single green run is not enough confidence for a race-sensitive test.
