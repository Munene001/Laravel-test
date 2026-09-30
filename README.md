# Orders API (Laravel Take-Home)

A lightweight Laravel API that reads `orders.json` and exposes endpoints for
listing, filtering, and fetching orders.

---

## Quick Start

```bash
composer install
php artisan serve
```

- **Data file:** `storage/app/json/data.json`
- **Base URL:** `http://localhost:8000/api`
-

---

## Endpoints

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/orders` | List all orders with computed `total` |
| `GET` | `/api/orders?status=paid` | Filter by status |
| `GET` | `/api/orders?customer_id=1` | Filter by customer ID |
| `GET` | `/api/orders?status=paid&customer_id=1` | Filter by status and customer ID |
| `GET` | `/api/orders/{id}` | Single order lookup |
| `GET` | `/api/customers/{id}/orders` | Customer orders lookup *(bonus)* |

---



---

## Curl Examples

```bash
curl "http://localhost:8000/api/orders"
curl "http://localhost:8000/api/orders?status=paid"
curl "http://localhost:8000/api/orders?customer_id=1"
curl "http://localhost:8000/api/orders?status=paid&customer_id=1"
curl "http://localhost:8000/api/orders/1003"
curl "http://localhost:8000/api/customers/1/orders"
```

---

## Error Handling

| Scenario | Code | Response Body |
|---|---|---|
| Data file missing | `404` | `{"error": "File not found"}` |
| Invalid JSON syntax | `500` | `{"error": "Invalid orders data"}` |
| Invalid `customer_id` | `422` | `{"error": "Invalid customer_id parameter"}` |
| Invalid order/customer id | `422` | `{"error": "Invalid order id"}` / `{"error": "Invalid customer id"}` |
| Order not found | `404` | `{"error": "Order not found"}` |
| Customer has no orders | `200` | `[]` |

**Note:** Empty collections return `200 []` (valid request, empty result);
missing resource lookups return `404`.

---

## Architecture & Design

All logic lives in `OrderController` with two private helpers:

- **`orders()`** — reads and validates JSON; triggers HTTP errors on missing
  or corrupted files.
- **`withTotal()`** — computes order line-item totals.

### Implementation Details

- **Reindexing:** `array_values()` after filtering ensures JSON arrays encode
  as sequential lists (`[...]`) rather than key-value objects.
- **Type-safe parsing:** string path/query params converted before matching
  numeric IDs.
- **Null-safety:** `??` fallbacks prevent undefined array key errors.

---

## Technical Discussion (Part 5)

### 1. Scaling to 1,000,000 orders

The current design reads the whole file into memory on every request and
filters/aggregates in PHP. At a million rows that loads a million records per
request — fatal.

- **Move to a database.** Migrate to `customers`, `orders`, and `order_items`
  tables so queries are indexed instead of scanned in memory.
- **Filter in SQL, not PHP.** Use `WHERE status = ? AND customer_id = ?`
  instead of `array_filter`. Index `status`, `customer_id`, and a **composite
  `(status, customer_id)`** — without indexes, every filter is a full table
  scan.
- **Aggregate totals in SQL** with
  `SUM(order_items.quantity * order_items.unit_price)` via a join. Computing
  this in PHP means loading every line item into memory.
- **Paginate.** A million rows can never be returned in one response. Cap
  `per_page` (e.g. 15–100) so the API stays responsive.
- **Eager-load items** (`with('items')`) to avoid the N+1 query problem when
  serializing orders.

> Trade-off: this trades the flat file's simplicity for schema and query
> complexity — but at a million rows there's no alternative.

### 2. Input validation

At the **boundary** — where untrusted input enters the app, before any
business logic runs. Validate once, then trust the data internally.

- **Form Requests** for query/body params (`OrderIndexRequest`) — return `422`
  automatically on failure and keep the controller clean.
- **Route constraints** (`->whereNumber('id')`) for path params, so a
  malformed id never reaches the controller.

### 3. Order not found

**`404 Not Found`** — the resource genuinely doesn't exist.

- `422` is for a *malformed request* (e.g. a non-numeric id), not a valid
  lookup that misses.
- For an order the user isn't allowed to see, prefer `404` over `403` so you
  don't leak that the order exists. `403` confirms existence; `404` reveals
  nothing.

### 4. API security

Authentication and authorization are separate concerns.

- **Authentication (who are you?):** Sanctum tokens via `auth:sanctum`
  middleware → `401` before the controller runs. The check belongs in
  middleware, not in every method.
- **Authorization (are you allowed?):** Laravel **Policies** → `403` when
  authenticated but not permitted.
- **Never trust client-supplied identity.** Derive the user from the token
  (`auth()->id()`), never from `customer_id` in the query — otherwise anyone
  can read anyone's orders by changing the param.
- Add rate limiting (`throttle`), HTTPS only, and short-lived tokens.

### 5. Third-party payment / shipping integration

**Key storage**
- Store in `.env`, read via `config('services.x.key')` — never in code or
  version control. Use `config()`, not `env()`, because `env()` returns null
  once config is cached.
- In production, use a **secrets manager** (AWS Secrets Manager, Vault) rather
  than a plain file on disk.
- Never log the key or return it in a response.

**On timeout**
- Set an explicit `Http::timeout(10)` — never let a call hang.
- **Retry transient failures** (timeouts, `5xx`) with backoff — but **only
  with an idempotency key**, and **never blindly retry a payment charge**, or
  you risk double-charging.
- Distinguish failures: timeouts/`5xx` are retryable; `4xx` are not.
- **Queue the call** so it doesn't block the user's request. Return `202
  Accepted`, process async, reconcile later.
- Fail gracefully — mark the order pending, record the attempt, alert on
  failure spikes.

**Testing**
- **`Http::fake()`** in tests — mock the provider and assert the payload with
  `Http::assertSent(...)`.
- Use the provider's **sandbox / test mode** for integration-level checks.
- **Hide the client behind an interface** (`PaymentGateway`) so a fake can be
  injected and your logic unit-tested with no HTTP at all.

### 5. Third-Party API Integration
- **Credentials:** store in `.env`, load through `config/services.php`, exclude
  from version control.
- **Resilience:** wrap calls with `Http::timeout()`, retry transient failures
  with backoff and idempotency keys, offload to async queue jobs.
- **Testing:** mock external calls with `Http::fake()`.
