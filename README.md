Markdown# Orders API (Laravel Take-Home)

A lightweight Laravel API reading `orders.json` to expose listing, filtering, detail, and customer order endpoints.

---

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
Data File: storage/app/json/data.jsonBase URL: http://localhost:8000/apiNo database or seeders required.EndpointsMethodURIDescriptionGET/api/ordersList all orders with computed totalGET/api/orders?status=paidFilter by statusGET/api/orders?customer_id=1Filter by customer IDGET/api/orders?status=paid&customer_id=1Filter by status and customer IDGET/api/orders/{id}Single order lookupGET/api/customers/{id}/ordersCustomer orders lookup (Bonus)Sample Responsetotal is computed dynamically as sum(quantity × unit_price):JSON{
  "id": 1003,
  "customer": { "id": 1, "name": "Jane Wanjiku" },
  "status": "paid",
  "items": [
    { "name": "Laptop Stand", "quantity": 1, "unit_price": 3500 }
  ],
  "total": 3500
}
Curl ExamplesBashcurl "http://localhost:8000/api/orders"
curl "http://localhost:8000/api/orders?status=paid"
curl "http://localhost:8000/api/orders?customer_id=1"
curl "http://localhost:8000/api/orders?status=paid&customer_id=1"
curl "http://localhost:8000/api/orders/1003"
curl "http://localhost:8000/api/customers/1/orders"
Error HandlingScenarioCodeResponse BodyData file missing404{"error": "File not found"}Invalid JSON syntax500{"error": "Invalid orders data"}Invalid query parameter (customer_id)422{"error": "Invalid customer_id parameter"}Invalid path parameter (id)422{"error": "Invalid order id"} / {"error": "Invalid customer id"}Order ID not found404{"error": "Order not found"}Customer has no orders200[]Note: Empty collections return 200 [] (valid request, empty result), whereas missing resource lookups return 404.Architecture & DesignAll logic is encapsulated in OrderController using two private helpers:orders(): Reads and validates JSON data; triggers HTTP errors on missing or corrupted files.withTotal(): Computes order line-item totals.Implementation DetailsReindexing: Uses array_values() post-filtering to guarantee JSON response arrays encode as sequential lists ([...]) rather than key-value objects.Type-Safe Parsing: Converts string path/query parameters to integers before matching numeric IDs.Null-Safety: Applies standard null-coalescing fallbacks (??) to prevent undefined array key errors.Technical Discussion (Part 5)1. Scaling to 1,000,000 OrdersDatabase: Migrate JSON to relational tables (orders, order_items, customers).Indexes: Index status, customer_id, and composite (status, customer_id) columns.SQL Aggregation & Pagination: Calculate totals using SQL (SUM(quantity * unit_price)) and paginate responses (paginate(15)).2. Input ValidationPerform at the request boundary using Laravel Form Requests for query/body validation and Route Constraints (->whereNumber('id')) for path parameters.3. Resource Not Found StatusReturn 404 Not Found for missing single resources. Use 404 over 403 if resource existence should remain private.4. API SecurityWrap routes in auth:sanctum middleware to enforce token authentication (401 on failure). Combine with Laravel Policies (403) for resource authorization.5. Third-Party API IntegrationCredentials: Store in .env, load through config/services.php, and exclude from version control.Resilience: Wrap calls with Http::timeout(), retry transient failures using backoff and idempotency keys, and offload processing to asynchronous Queue jobs.Testing: Mock external calls using Http::fake() during automated test execution.
