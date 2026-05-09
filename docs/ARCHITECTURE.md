# Architecture

How the codebase is laid out and the design choices behind it. For the
runtime concurrency story see [CONCURRENCY.md](CONCURRENCY.md); for the
HTTP surface see [API.md](API.md).

## Layout

```
app/
  Models/                                  anemic Eloquent — relationships, casts, scopes
  Domain/
    Cart/
      Actions/CalculateCartQuoteAction
      Services/CartCalculator              single source of truth for math
      Data/{CartItemData, CartQuoteData, CalculatedLine, CartQuoteResult}
      Exceptions/{ProductNotFound, ProductInactive, MixedCurrency}
    Reservation/
      Actions/{Create, Release, Expire}ReservationAction
      Services/InventoryGuard              atomic conditional UPDATE
      Enums/ReservationStatus
      Exceptions/{InsufficientStock, ReservationAlreadyReleased,
                  ReservationNotActive, IdempotencyConflict}
      Data/CreateReservationData
    Product/
      Queries/ProductListQuery             N+1-free listing
      Data/ProductFilters
  Support/
    Idempotency/IdempotencyStore           Redis-backed get/put
  Http/
    Controllers/Api/{Product, Cart, Reservation, Health}Controller
    Requests/{CartQuote, CreateReservation}Request   FormRequest → DTO
    Resources/{Product, Reservation, ReservationItem}Resource
    Middleware/{Idempotency, RequestId}Middleware
  Jobs/ExpireReservationsJob               scheduled every minute
```

## Why Action classes, not service classes

Each business operation is a `final` Action with constructor-injected
dependencies (`CreateReservationAction`, `ReleaseReservationAction`, etc.) —
one public method, one responsibility, no fat services that drift into
400-line god objects. Controllers stay ≤ 10 lines (`validate → dispatch
action → return resource`); the action owns the transaction boundary and
orchestrates services. Adding a new endpoint adds a new action — never
modifies an existing one — which keeps the blast radius small under feature
pressure.

## Why models stay in `app/Models/`

Laravel's ecosystem (factories, policies, broadcasting, Nova/Filament, queue
serialisation, auto-discovery) all assume `App\Models\*`. Moving them under
`app/Domain/*/Models/` costs more than it gains. Models stay anemic — only
relationships, casts, scopes, and accessors; business logic lives in
`app/Domain/`. The discipline is "no Eloquent in controllers, no business
logic in models," not "no models in `Models/`."

## Single source of truth for cart math

`CartCalculator::calculate()` is called by both `CalculateCartQuoteAction`
(the `/cart/quote` endpoint) and `CreateReservationAction` (the
`/reservations` endpoint). The reservation does **not** re-derive prices
from the request — it routes through the same calculator the quote uses.
This makes the bug class "quote shows 259.80 TL but reservation charges
261.00 TL" structurally impossible: there is no second code path to drift.

## Where each concern lives

| Layer | Owns | Does NOT own |
|---|---|---|
| Controller | Validate → call Action → return Resource | Business rules, queries, conditionals |
| FormRequest | Schema validation, authorize | Domain rules ("is stock sufficient") |
| Resource | JSON shape | Calculation |
| Action | Transaction boundary, orchestration | Low-level SQL — delegates to Service |
| Service (`CartCalculator`, `InventoryGuard`) | Pure domain logic, repeated math | HTTP, transactions |
| Query (`ProductListQuery`) | Read-only complex queries | Writes, transactions |
| Model | Eloquent (relations, casts, scopes) | Business rules |
| Middleware | Cross-cutting (idempotency, request id, throttle) | Business |

## Migrations write raw SQL on purpose

```php
DB::statement('ALTER TABLE inventories
    ADD CONSTRAINT reserved_lte_quantity CHECK (reserved_quantity <= quantity)');
```

Laravel's Schema Builder does not express PostgreSQL `CHECK` constraints
directly. Rather than fake portability across databases the case never
required, the migrations lean into Postgres-specific features (`ILIKE`,
`gen_random_uuid()`, partial indexes, CHECK constraints). The schema is
treated as a contract enforced by the database, not a portable abstraction
— see [CONCURRENCY.md](CONCURRENCY.md) for why this matters.
