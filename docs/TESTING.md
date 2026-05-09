# Testing

The Pest suite is split into three groups so the fast tests stay fast and
the slow concurrency test stays isolated.

```bash
# Default — Unit + Feature (sequential, in-process)
vendor/bin/pest --testsuite=Unit,Feature
# 38 passed, 136 assertions, ~1.5s

# Concurrency — spawned server + parallel HTTP fan-out
vendor/bin/pest --testsuite=Concurrency
# 1 passed, 6 assertions, ~1.4s

# All
vendor/bin/pest
```

The test database (`mini_cart_test`) is separate from dev (`mini_cart`);
see `phpunit.xml`.

## Suite layout

| Suite | What it covers | Isolation |
|---|---|---|
| Unit | `CartCalculator` math, pure domain helpers | `RefreshDatabase` not needed |
| Feature | Every endpoint — happy path + error cases + DB CHECK proof | `RefreshDatabase` |
| Concurrency | `CreateReservationConcurrencyTest` — 10 parallel HTTP requests against stock = 5 | `DatabaseMigrations` (server lives across the test) |

## The concurrency test

`tests/Concurrency/CreateReservationConcurrencyTest.php` spawns a real PHP
HTTP server, then uses Guzzle Pool to fan out 10 simultaneous `POST
/api/reservations` requests against a product with `quantity = 5`.
Outcome must be exactly **5 × 201 + 5 × 409**, with `reserved_quantity = 5`
in the database afterwards. Anything else is a race condition.

A single green run isn't enough — CI runs the same test 10× consecutively
to catch flaky outcomes:

```bash
for i in 1..10; do vendor/bin/pest --testsuite=Concurrency; done
```

## N+1 enforcement

`ProductListingTest` enables the query log, hits `/api/products`, and
asserts the count is ≤ 4 regardless of result-set size. If anyone adds an
unguarded `$product->activePrice` access to a Resource, this test fails —
the regression is structural, not stylistic.

## CHECK constraint proof

The Feature suite includes a test that manually attempts an
`UPDATE inventories SET reserved_quantity = quantity + 1`, asserts the
`QueryException` is raised, and checks the PostgreSQL error code is
`23514`. This proves the "stock can never go negative" invariant is
guaranteed by the database, not just by the application.
