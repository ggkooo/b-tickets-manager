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

### Run on the Network / HTTPS

`php artisan serve` is a development server — it's not meant to be exposed
directly to the network or to terminate TLS. In production this API sits
behind a [Caddy](https://caddyserver.com) reverse proxy that owns the
certificate and forwards `/api/*` and `/storage/*` to it, so the server only
needs to bind to `localhost`:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Set `APP_URL` in `.env` to the **public HTTPS address** (the one Caddy
serves), not `localhost` — this is what `Storage::url()` uses to build
uploaded-file URLs (e.g. TV videos), and if it's wrong those come back as
plain `http://`, which browsers block as mixed content on an HTTPS page:

```env
APP_URL=https://200.132.193.104:8443
TRUSTED_PROXIES=*
```

`TRUSTED_PROXIES=*` (already the default) is required so Laravel trusts the
`X-Forwarded-Proto` header Caddy sends — without it, Laravel thinks every
request is plain HTTP even when the client connected over HTTPS.

See the reverse proxy / TLS setup itself — including why it listens on 8443
instead of 443, and how to trust its certificate on other devices — in
[`../Caddyfile`](../Caddyfile) and the note at the top of it.

## Testing

```bash
php artisan test
```

## License

Proprietary.
