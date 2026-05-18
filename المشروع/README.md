# Parallel Programming Main (Laravel)

## 1. Project Overview
This project now includes:
- Functional e-commerce APIs (products, cart, checkout)
- Non-functional Requirement 1 (data integrity and race-condition protection)
- Non-functional Requirement 4 (batch processing for daily sales reporting)
- Non-functional Requirement 5 (load distribution simulation)

The focus is to demonstrate practical backend behavior under concurrency and load, while staying compatible with PHP 8.2 and Laravel 11.

## 2. Existing Project Analysis Summary
### Before merge (`Parallel-programming-main`)
- Laravel 11 (`laravel/framework ^11.31`) and PHP `^8.2`
- Existing functional APIs: auth, product listing, cart, checkout
- Existing Non-functional Requirement 1: safe checkout with locking (`checkoutWithLock`) and transaction/lock flow
- Existing schemas for products, orders, order_items, carts, cart_items, users

### Imported from `my-app` and adapted
- Daily sales report before/after processing endpoints
- Chunked reporting service using `chunkById()`
- Background job for report generation and persistence
- `daily_sales_reports` storage model/migration
- `server-info` endpoint for multi-instance identity
- Nginx round-robin config and k6 scripts
- `benchmark:seed` command adapted to target schema (`total_price`, `price`, `stock_quantity`)

## 3. Functional Requirements
Current main functional APIs include:
- `GET /api/products`
- `POST /api/cart/add`
- `POST /api/checkout`
- `POST /api/checkoutWithLock`
- `POST /api/register`
- `POST /api/login`

Core models in current project:
- `Product`
- `Cart`, `CartItem`
- `Order`, `OrderItem`
- `User`

## 4. Non-Functional Requirement 1 (Existing)
The project already protects shared data against race conditions in checkout flow using lock-based and transactional handling (`SafeOrderController`).

This existing implementation was preserved and not replaced.

## 5. Non-Functional Requirement 4: Batch Processing
Batch processing was added for daily sales reporting.

### Endpoints
- Baseline (without chunking):
  - `GET /api/reports/daily-sales/before`
- Optimized (with chunking):
  - `GET /api/reports/daily-sales/after`
- Background processing job dispatch:
  - `POST /api/reports/daily-sales/job`

### How it works
- Service: `app/Services/Reports/DailySalesReportService.php`
- Job: `app/Jobs/ProcessDailySalesReportJob.php`
- Storage: `daily_sales_reports` table
- `chunkById()` is used in the optimized path to reduce memory pressure and improve stability with larger datasets.

### Metrics returned
- `processing_mode`
- `execution_time_ms`
- `memory_usage_mb`
- `memory_peak_mb`
- `total_processed_orders`
- `total_sales`
- `total_items`
- `chunk_size` (after endpoint)

### Comparison table template
| Test | Execution Time | Memory Usage | Peak Memory | Total Orders | Notes |
|------|----------------|--------------|-------------|--------------|-------|
| Before | | | | | Loads records at once |
| After | | | | | Uses chunkById |

## 6. Non-Functional Requirement 5: Load Distribution
### Server-info endpoint
- `GET /api/system/server-info`
- Returns: `app_instance`, `server_port`, `process_id`, `hostname`, `timestamp`, `request_id`

### Run 3 Laravel instances (Windows PowerShell)
Terminal 1:
```powershell
$env:APP_INSTANCE="laravel-node-1"
php artisan serve --host=127.0.0.1 --port=8001
```
Terminal 2:
```powershell
$env:APP_INSTANCE="laravel-node-2"
php artisan serve --host=127.0.0.1 --port=8002
```
Terminal 3:
```powershell
$env:APP_INSTANCE="laravel-node-3"
php artisan serve --host=127.0.0.1 --port=8003
```

### Linux / Git Bash style
```bash
APP_INSTANCE=laravel-node-1 php artisan serve --host=127.0.0.1 --port=8001
APP_INSTANCE=laravel-node-2 php artisan serve --host=127.0.0.1 --port=8002
APP_INSTANCE=laravel-node-3 php artisan serve --host=127.0.0.1 --port=8003
```

### Nginx load balancer
- Config file: `deployment/nginx/load-balancer.conf`
- Uses round-robin across ports `8001`, `8002`, `8003`
- Exposes port `8080`

Common issue:
- If `http://localhost:8080/...` fails, Nginx is likely not running or not reloaded.
- Laravel instances alone do not open port `8080`.

### Why Round Robin
- Simple and easy to explain
- Suitable for equal-capacity local Laravel instances
- Good for university demonstration of horizontal scaling
- No complex state management needed

## 7. k6 Testing
Run from project root:
```bash
k6 run tests/k6/batch-before.js
k6 run tests/k6/batch-after.js
k6 run tests/k6/load-distribution.js
```

Docker alternative (if `k6` not installed):
```bash
docker run --rm -i grafana/k6 run - < tests/k6/batch-before.js
docker run --rm -i grafana/k6 run - < tests/k6/batch-after.js
docker run --rm -i grafana/k6 run - < tests/k6/load-distribution.js
```

## 8. Setup and Run Instructions
```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set MySQL connection in `.env` (recommended for performance tests), then:
```bash
php artisan migrate
php artisan benchmark:seed
php artisan serve
php artisan queue:work
```

Optional benchmark reset:
```bash
php artisan benchmark:seed --reset
```

Larger benchmark sample:
```bash
php artisan benchmark:seed --products=1000 --orders=50000 --order-items=100000
```

## 9. Requirement 4/5 Test Commands
### Batch processing endpoints
```bash
curl http://127.0.0.1:8000/api/reports/daily-sales/before
curl http://127.0.0.1:8000/api/reports/daily-sales/after
curl -X POST http://127.0.0.1:8000/api/reports/daily-sales/job
```

### Load distribution endpoints
```bash
curl http://127.0.0.1:8001/api/system/server-info
curl http://127.0.0.1:8002/api/system/server-info
curl http://127.0.0.1:8003/api/system/server-info
curl http://localhost:8080/api/system/server-info
```

## 10. Files Added/Modified
### Added
- `app/Models/DailySalesReport.php`
- `app/Services/Reports/DailySalesReportService.php`
- `app/Jobs/ProcessDailySalesReportJob.php`
- `app/Http/Controllers/DailySalesReportController.php`
- `app/Http/Controllers/SystemStatusController.php`
- `app/Http/Controllers/BenchmarkController.php`
- `app/Console/Commands/BenchmarkSeedCommand.php`
- `database/migrations/2026_05_15_000001_create_daily_sales_reports_table.php`
- `database/factories/ProductFactory.php`
- `database/factories/OrderFactory.php`
- `database/factories/OrderItemFactory.php`
- `tests/k6/batch-before.js`
- `tests/k6/batch-after.js`
- `tests/k6/load-distribution.js`
- `deployment/nginx/load-balancer.conf`

### Modified
- `routes/api.php` (added requirement 4/5 endpoints + benchmark trigger)
- `app/Models/Product.php` (factory support + relation)
- `app/Models/Order.php` (relations for reports)
- `app/Models/OrderItem.php` (relations for reports)
- `README.md` (project-specific merged documentation)