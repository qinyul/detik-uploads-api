# Laravel API – CSV Bulk Upload

This project uses a **simple, pragmatic architecture** optimized for fast development:

-   **Laravel runs locally** using `php artisan serve`
-   **Docker is used only for infrastructure** (PostgreSQL & Redis)
-   **Docker** is optional, not mandatory
-   Use Docker **only** if PostgreSQL / Redis are not installed locally
-   **Single `.env` file** shared by Laravel and Docker Compose
-   No PHP, Composer, or Laravel runtime inside Docker

This setup is ideal for:

-   API-only backends
-   Solo developers or small teams
-   Fast iteration without Docker rebuilds

---

## 🐳 Docker Usage (Optional)

Docker is **optional**.

### Use Docker if:

-   You don’t want to install PostgreSQL / Redis locally
-   You want consistent infra across machines

### Skip Docker if:

-   PostgreSQL is already installed locally
-   Redis is already installed locally

Laravel works in **both cases**.

---

## 🛠 Makefile (Optional)

A `Makefile` is provided for convenience:

```bash
make up
```

What it does:

1. Starts docker-compose **only if not running**
2. Runs `php artisan serve`

### Windows users

-   `make` is usually **not available by default** on Windows
-   Options:

    -   Use **WSL**
    -   Run Docker & Artisan commands manually

---

## Architecture Overview

```
Host Machine
├─ PHP (artisan serve)
├─ Laravel Application
└─ Docker
   ├─ PostgreSQL
   └─ Redis
```

**Key principles**:

-   Laravel always runs locally
-   `.env` = single source of truth

---

## Requirements

### Host Machine

-   PHP 8.3+
-   Composer
-   Docker & Docker Compose

### PHP Extensions

Ensure these extensions are enabled locally:

-   `pdo_pgsql`
-   `redis` (phpredis) or use `predis/predis`

Verify:

```bash
php -m | grep -E "pdo_pgsql|redis"
```

---

## Environment Configuration

This project uses **one `.env` file** for both Laravel and Docker Compose.

### `.env`

| Category           | Variable                   | Description                             |
| ------------------ | -------------------------- | --------------------------------------- |
| PostgreSQL         | POSTGRES_VERSION           | PostgreSQL Docker image version         |
| PostgreSQL         | POSTGRES_DB                | Database name                           |
| PostgreSQL         | POSTGRES_USER              | Database username                       |
| PostgreSQL         | POSTGRES_PASSWORD          | Database password                       |
| PostgreSQL         | POSTGRES_PORT              | Exposed PostgreSQL port                 |
| Redis              | REDIS_VERSION              | Redis Docker image version              |
| Redis              | REDIS_HOST                 | Redis host (`127.0.0.1`)                |
| Redis              | REDIS_PORT                 | Redis port                              |
| Product Import CSV | IMPORT_PRODUCTS_BATCH_SIZE | CSV read batch size per queue iteration |

Example:

```env
APP_NAME=Laravel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

PHP_VERSION=8.3

POSTGRES_VERSION=16
POSTGRES_DB=laravel
POSTGRES_USER=laravel
POSTGRES_PASSWORD=secret
POSTGRES_PORT=5432

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=${POSTGRES_PORT}
DB_DATABASE=${POSTGRES_DB}
DB_USERNAME=${POSTGRES_USER}
DB_PASSWORD=${POSTGRES_PASSWORD}

REDIS_VERSION=7
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

> ⚠️ Commit `.env.example`, not `.env`

---

## Docker Compose (Infrastructure Only)

Start infrastructure:

```bash
docker-compose up -d
```

---

## Running the Application

### Option A: Mixed Setup (Common for Experienced Devs)

-   PostgreSQL: local installation
-   Redis: local installation
-   Laravel: local (`php artisan serve`)

This option avoids Docker entirely and is fully supported.

Choose the setup that best matches your environment and experience level.

```bash
php artisan serve
```

### Option B: Using Makefile (macOS / Linux)

A `Makefile` is provided to simplify development.

```makefile
.PHONY: up serve dev

up:
	@docker-compose ps | grep -q "postgres" || docker-compose up -d

serve:
	php artisan serve

dev:
	make up && make serve
```

Usage:

```bash
make dev
```

What this does:

-   Starts Docker infrastructure **only if it is not already running**
-   Runs `php artisan serve` locally

This avoids restarting containers unnecessarily.

---

### Option C: Without Makefile (Windows-friendly)

If `make` is not available (common on Windows), run commands manually:

```bash
docker compose up -d
php artisan serve
```

Docker Compose is **idempotent** — running it multiple times is safe.

Application will be available at:

```
http://localhost:8000
```

---

## 🧩 Tech Stack

-   PHP 8.2+
-   Laravel (latest)
-   Laravel Sanctum (API auth)
-   PostgreSQL
-   Redis
-   Docker & Docker Compose (optional)

---

## 📁 Directory Structure

```
app/
 ├── Http/
 │   ├── Controllers/Api/V1/
 │   └── Requests/Api/V1/
 ├── Services/
 │   └── Auth/
 │       └── AuthService.php
```

---

## ✅ Design Principles

-   Laravel conventions first
-   Minimal abstraction
-   Explicit over magic
-   Transactions for business safety
-   Optional Docker, never mandatory

---

## 🔐 Authentication Design

-   Token-based authentication using **Laravel Sanctum**
-   Clean separation of concerns:

```
Controller → Service → Model
```

### Why Service Layer?

-   Thin controllers
-   Testable business logic
-   Centralized transactions & logging

---

## 📦 Import Products Flow (Authenticated)

> Import products requires **login** and a valid Sanctum token.

### End-to-End Flow

1. User logs in via `POST /api/v1/login`
2. API returns Sanctum access token
3. Client sends request to `POST /api/v1/imports/products`

    - Header: `Authorization: Bearer {token}`
    - Body: CSV file upload

4. API stores CSV file in `storage/app/imports`
5. API creates `import_jobs` record with status `pending`
6. API dispatches `ImportProductsJob` to **Redis queue**
7. Queue worker processes the job:

    - Update status to `in_progress`
    - Read CSV file from storage
    - Bulk insert / upsert products
    - Update `total`, `success`, `failed`
    - Mark job as `completed`

8. If job fails:

    - Status updated to `failed`
    - CSV file is **not deleted** (kept for debugging or retry)

---

## 📝 Import Job Design Notes

-   Import processing is **asynchronous** using Redis queue
-   Database is the **source of truth** for job state
-   Redis is used only for execution, never for persistence
-   File storage is required because queue jobs are async
-   CSV file deletion happens:

    -   ✅ After successful job completion
    -   ❌ Not on failure

-   File deletion is part of the **same queue job lifecycle**
-   No additional cleanup job or queue is created

---

## 🧩 Batch / Chunk Processing Strategy

-   Why chunking is used
    -   Prevents loading the entire CSV into memory
    -   Allows progress tracking on large files
    -   Works well with queue workers
-   Important distinction

    -   Precise error reporting
    -   Exact row number logging
    -   Partial success (bad rows don’t break good ones)

-   ⚙️ Batch Size Configuration

```
    // config/import.php

    return [
        'products_batch_size' => env('IMPORT_PRODUCTS_BATCH_SIZE', 500),
    ];
```

---

## 🧪 Error Handling Strategy

-   Services may throw exceptions
-   Controllers **do not catch** exceptions
-   Laravel global exception handler returns JSON responses

This avoids:

-   Duplicate try/catch logic
-   Swallowed exceptions
-   Incorrect HTTP status codes

---

## 📁 Directory Structure

```
app/
 ├── Http/
 │   ├── Controllers/Api/V1/
 │   └── Requests/Api/V1/
 ├── Services/
 │   ├── Auth/
 │   │   └── AuthService.php
 │   └── Import/
 │       └── ProductImportService.php
 ├── Jobs/
 │   └── ImportProductsJob.php
 ├── Models/
 │   ├── User.php
 │   ├── Product.php
 │   └── ImportJob.php

```

---

## 🔌 API Documentation (Postman)

This repository includes a Postman Collection to help you test the API endpoints efficiently.

### 📥 Import Guide

1.  Download the files located in the `postman/` directory:
    -   [📄 Download Collection](./postman-collection/detik-test.postman_collection.json)
    -   [📄 Download Environment](./postman-collection/detik-test.postman_environment.json)
2.  Open Postman and click **Import** (Top left).
3.  Select the **"Laravel Local"** environment in the top right dropdown.

### 🚀 Automated Workflow

This collection uses **Postman Scripts** to automate variables. You do not need to copy-paste tokens or IDs manually.

1.  **Step 1: Authenticate**

    -   Send a request to **Register** or **Login**.
    -   The script will **automatically** extract the `access_token` and save it to your environment.

2.  **Step 2: Upload CSV**

    -   Go to the **Import Products** request.
    -   Upload your CSV file in the **Body** tab.
    -   Upon success, the script will **automatically** save the returned `job_id` to the environment.

3.  **Step 3: Check Progress**
    -   Go to the **Check Job Status** request.
    -   Just click **Send**.
    -   The URL uses `{{job_id}}` variable which was auto-populated from Step 2.
