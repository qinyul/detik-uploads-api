# Laravel API – Local Runtime + Docker Infra

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

## ▶️ Running with Docker (Infra Only)

```bash
docker compose up -d
```

This starts:

-   PostgreSQL
-   Redis

Then run Laravel locally:

```bash
php artisan serve
```

---

## ▶️ Running Without Docker

Ensure PostgreSQL & Redis are running locally, then:

```bash
php artisan serve
```

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

| Category   | Variable          | Description                     |
| ---------- | ----------------- | ------------------------------- |
| PostgreSQL | POSTGRES_VERSION  | PostgreSQL Docker image version |
| PostgreSQL | POSTGRES_DB       | Database name                   |
| PostgreSQL | POSTGRES_USER     | Database username               |
| PostgreSQL | POSTGRES_PASSWORD | Database password               |
| PostgreSQL | POSTGRES_PORT     | Exposed PostgreSQL port         |
| Redis      | REDIS_VERSION     | Redis Docker image version      |
| Redis      | REDIS_HOST        | Redis host (`127.0.0.1`)        |
| Redis      | REDIS_PORT        | Redis port                      |

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
docker compose up -d
```

---

## Running the Application

### Option A: Using Makefile (macOS / Linux)

A `Makefile` is provided to simplify development.

```makefile
.PHONY: up serve dev

up:
	@docker compose ps | grep -q "postgres" || docker compose up -d

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

### Option B: Without Makefile (Windows-friendly)

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
