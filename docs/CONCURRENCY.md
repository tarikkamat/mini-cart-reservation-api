# Concurrency & Idempotency

The interesting part of the case. Two independent problems handled with
two different mechanisms:

1. **Stock under concurrent reservations** → atomic conditional UPDATE +
   PostgreSQL CHECK constraints, no application-level locks.
2. **Duplicate requests across retries** → Redis-backed idempotency cache +
   distributed lock around the same key.

Verified by `tests/Concurrency/CreateReservationConcurrencyTest.php`,
which spawns a real HTTP server and fans 10 parallel requests at a product
with stock = 5. Outcome is consistently **5 × 201 + 5 × 409**, with
`reserved_quantity = 5`. CI runs it 10× per push.

## Available stock — counter approach

`available_stock` is computed from a **counter** on the `inventories` row:
`available = quantity - reserved_quantity`. The alternative —
`SUM(active_reservations)` on demand — is correct but expensive on every
read.

The counter is kept correct by:

1. **Atomic conditional UPDATE** in `InventoryGuard::reserveMany`:
   ```sql
   UPDATE inventories
   SET reserved_quantity = reserved_quantity + ?
   WHERE product_id = ? AND (quantity - reserved_quantity) >= ?
   ```
   `affected = 0` means no stock; the action throws
   `InsufficientStockException`.

2. **PostgreSQL CHECK constraints** as the last line of defence:
   ```sql
   reserved_quantity >= 0
   reserved_quantity <= quantity
   quantity >= 0
   ```
   Even with an application bug, Postgres rejects the write. A Feature test
   (`it('enforces the reserved_lte_quantity CHECK …')`) verifies this
   directly — the constraint is part of the contract, not decoration.

The `stock_movements` table is an immutable audit log (`created_at` only,
no `updated_at`) capturing every reserve/release/expire/adjustment with
`quantity_delta`, `reserved_after`, and `quantity_after`.

## Why no `lockForUpdate` on inventory

Lock-based stock decrement is correct but serialises the hot row, hurting
throughput under contention. The atomic UPDATE-with-WHERE-guard pattern
achieves the same correctness without application locks, because
PostgreSQL's row-level atomicity already serialises the read+write inside
a single statement. This is the **compare-and-set** pattern (a.k.a.
optimistic concurrency, conditional update).

## Deadlock prevention

A multi-item reservation that touches two products in different orders
across two concurrent requests is the textbook deadlock. The Action sorts
items by `product_id` ascending before reserving, so any two concurrent
multi-item reservations always acquire row locks in the same order. Lock
ordering is the standard fix, applied at the only place it matters.

## Where `lockForUpdate` *is* used

On the `Reservation` row during release/expire — not inventory. It
serialises concurrent same-reservation operations so the status check is
atomic with respect to other releasers. That's what makes a double-release
a clean no-op rather than a double-restore of stock. Combined with the
`WHERE reserved_quantity >= ?` guard inside `InventoryGuard::releaseMany`,
double-release is defended at two layers.

## Idempotency — Redis as single source of truth

| Key | TTL | Mechanism | Purpose |
|---|---|---|---|
| `idem:{key}` | 24h (configurable) | `SETEX` JSON payload | Cached response replay |
| `idem-lock:{key}` | 10s | `Cache::lock()->block(5, …)` | Serialise concurrent same-key requests |

Flow on `POST /api/reservations` with an `Idempotency-Key` header:

1. `IdempotencyMiddleware` takes the lock and looks up `idem:{key}`.
2. **Cache hit, body hash matches** → replay the cached response with
   `Idempotent-Replay: true`. No second reservation is created.
3. **Cache hit, body hash differs** → `422 IDEMPOTENCY_CONFLICT`.
4. **Cache miss** → run the handler, cache the response (4xx + 2xx only —
   5xx is never cached so a transient failure doesn't poison the key).

The `Cache::lock("idem-lock:{key}")->block(5, …)` step makes concurrent
same-key requests serialise: the second waits for the first, then sees the
cached result instead of double-executing.

A separate `idempotency_keys` SQL table was deliberately rejected — Redis
has native TTL, distributed locking, and sub-millisecond latency. The
`reservations.idempotency_key` column exists only for forensic linking, and
the `unique` index on it is a last-line defence if Redis ever evicts the
key prematurely.

## Release endpoint is naturally idempotent

`POST /api/reservations/{id}/release` returns the current state with HTTP
200 even when called on an already-released, expired, or committed
reservation. No `Idempotency-Key` needed — the endpoint's semantics make
duplicate calls safe. Stock is restored exactly once.

## Expiration

`ExpireReservationsJob` runs every minute (scheduled, `withoutOverlapping`),
finds `status = 'active' AND expires_at < now()`, processes them via
`ExpireReservationAction` → `releaseMany`, and flips status to `expired`.
`chunkById` keeps memory flat at any backlog size.
