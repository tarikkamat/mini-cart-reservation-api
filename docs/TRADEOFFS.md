# Trade-offs — what I'd change for production

The case is intentionally small. These are the changes that would move the
codebase from "passes review" to "runs at scale," in the order I'd actually
do them.

- **Reservation creation as a queued job** under heavy load. The
  synchronous transaction is fast (~10 ms) but a queue absorbs spikes and
  gives natural backpressure.
- **Event-sourced inventory** for full audit trail and time-travel
  debugging. The current `stock_movements` log is the seed of this;
  replacing the counter with a fold over events is the natural next step.
- **Per-product write sharding** for hot products — partition `inventories`
  by `product_id % N` so concurrent UPDATEs hit different physical pages.
- **Multi-region:** distributed transactions / saga pattern for
  cross-warehouse stock, with eventual consistency on the read side.
- **Dedicated Redis cluster for idempotency**, isolated from cache/session
  traffic so hot-key contention from one workload doesn't block reservation
  acceptance.
- **Prometheus metrics** on reservation success/failure rates, lock
  contention, and Redis idempotency hit ratio. Pair with a dashboard that
  pages on a sustained drop in success rate.
- **Octane** (RoadRunner / FrankenPHP) once request volume justifies the
  cost of request-scoped state hygiene. Deliberately *not* used here —
  concurrency correctness in this app lives at the database layer, not the
  worker layer, so Octane adds risk without addressing the bottleneck.

## Things I deliberately did *not* add

A short list of "obvious" additions I rejected, with why:

- **Repository pattern** — `ProductListQuery` covers the only complex read.
  A repository per model would add ceremony without removing duplication.
- **`spatie/laravel-data`** — DTOs are `final readonly` classes with
  constructor property promotion. Adding the package buys nothing the
  language doesn't already give us.
- **Separate `idempotency_keys` SQL table** — Redis already has native TTL,
  distributed locking, and sub-millisecond latency. The
  `reservations.idempotency_key` unique column is enough as a forensic
  fallback if Redis ever evicts.
- **Authentication** — out of scope per the case spec ("`customer_email`
  only, no auth"). Sanctum is wired up so adding it is a one-commit change.
