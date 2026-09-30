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

### 1. Scaling to 1,000,000 Orders
- **Database:** migrate JSON to relational tables (`orders`, `order_items`,
  `customers`).
- **Indexes:** `status`, `customer_id`, and composite `(status, customer_id)`.
- **SQL aggregation & pagination:** compute totals with
  `SUM(quantity * unit_price)` and paginate responses (`paginate(15)`).

### 2. Input Validation
At the request boundary — Laravel **Form Requests** for query/body validation
and **route constraints** (`->whereNumber('id')`) for path parameters.

### 3. Resource Not Found Status
`404 Not Found` for missing single resources. Use `404` over `403` if resource
existence should remain private.

### 4. API Security
Wrap routes in `auth:sanctum` middleware to enforce token authentication
(`401` on failure). Combine with Laravel **Policies** (`403`) for resource
authorization.

### 5. Third-Party API Integration
- **Credentials:** store in `.env`, load through `config/services.php`, exclude
  from version control.
- **Resilience:** wrap calls with `Http::timeout()`, retry transient failures
  with backoff and idempotency keys, offload to async queue jobs.
- **Testing:** mock external calls with `Http::fake()`.
