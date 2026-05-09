# Mini Cart Reservation API

A Laravel 13 backend for a simplified e-commerce flow with stock
reservations. Optimised for **concurrency correctness, architectural
clarity, and code quality**.

**PHP 8.4 · Laravel 13 · PostgreSQL 17 · Redis 7 · Pest 4**

## Quick start

```bash
cp .env.example .env
make up         # builds + starts the dev stack (app, nginx, queue, scheduler, postgres, redis)
make fresh      # migrate:fresh --seed → 50 sample products
make test       # full Pest suite

curl http://localhost/api/health
# {"status":"ok","db":"ok","redis":"ok"}
```

`make help` lists every target. `make up-prod` brings up the production
stack with no dev overlay — useful for smoke-testing the prod image.

## Documentation

The README is intentionally short. Each focused doc below is independently
readable; start with whichever question you have:

- **[Architecture](docs/ARCHITECTURE.md)** — folder layout, the Action
  pattern, why models stay anemic, where each concern lives.
- **[Concurrency & Idempotency](docs/CONCURRENCY.md)** — the centre of
  this case. Counter-based stock, atomic conditional UPDATE, PostgreSQL
  CHECK constraints, deadlock prevention, Redis-backed idempotency.
- **[API reference](docs/API.md)** — endpoints, request/response shapes,
  the standard error envelope, every error code.
- **[Deployment](docs/DEPLOYMENT.md)** — Docker stack topology, the two
  Compose files, what a real production rollout would add.
- **[Testing](docs/TESTING.md)** — Unit / Feature / Concurrency suites,
  the parallel HTTP harness, N+1 enforcement, CHECK-constraint proof.
- **[Trade-offs](docs/TRADEOFFS.md)** — what I'd change for production,
  and the obvious additions I deliberately *didn't* make.

## What's worth pointing at first

If you only have ten minutes, look at:

1. `app/Domain/Reservation/Services/InventoryGuard.php` — the atomic
   conditional UPDATE that makes stock concurrency-safe without
   application-level locks.
2. `tests/Concurrency/CreateReservationConcurrencyTest.php` — 10 parallel
   HTTP requests against stock = 5, asserting exactly 5 × 201 + 5 × 409.
   CI runs it 10× per push.
3. `app/Domain/Cart/Services/CartCalculator.php` — the single source of
   truth shared by `/cart/quote` and `/reservations`, so a price drift
   between the two endpoints is structurally impossible.
4. `database/migrations/*_create_inventories_table.php` — the
   `reserved_lte_quantity` CHECK constraint that makes negative stock
   physically un-writeable, even with an application bug.
