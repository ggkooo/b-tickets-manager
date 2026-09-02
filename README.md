# Ticket Totem — API

REST API for a multi-institution queue management system, powering public ticket kiosks, attendant consoles, admin panels, and TV displays across multiple locations, with full data isolation per location.

## Overview

- **Framework**: Laravel + Sanctum
- **Auth**: Bearer tokens (Sanctum) for staff routes, plus a required API key on every `/api/*` route
- **Multi-tenancy**: each user, ticket, and printer belongs to a single location; institutions group related locations and their own service catalog
- **Roles**: operator, admin, and super admin (scoped per location)
- **Printing**: tickets are queued for printing automatically on creation

## Tech Stack

- PHP 8.2+, Laravel
- Sanctum (API tokens)
- Queue-based ticket printing (network and Windows-shared printers)

## Project Structure

```text
app/
├── Http/
│   ├── Controllers/   # Thin HTTP orchestration
│   ├── Requests/      # Validation
│   └── Resources/     # API response shaping
├── Models/
├── Policies/
├── Services/           # Business logic (report building, ticket lifecycle)
└── Support/            # Shared helpers (location/service resolution)
```

## Getting Started

### Prerequisites

- PHP 8.2+, Composer
- A Laravel-compatible database (SQLite, MySQL, PostgreSQL, ...)

### Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set an `APP_API_KEY` in `.env` — every API request must send it via the `X-API-KEY` header.

### Database

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
```

Seeding creates a default admin account (`admin` / `admin`) per location. Change these credentials before going to production.

### Run Locally

```bash
php artisan serve
```

The API is served at `http://localhost:8000/api`.

Ticket printing runs through a queue worker:

```bash
php artisan queue:work
```

### Run on the Network

To make the API reachable from other devices on the network, bind the server to all interfaces:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

It will then be reachable at:

```text
http://<host-machine-ip>:8000/api
```

For example, on this project's host machine:

```text
http://200.132.193.104:8000/api
```

## Testing

```bash
php artisan test
```

## License

Proprietary.
