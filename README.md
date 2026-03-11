# B-Unilab API

Backend REST API for the Unilab totem system. Built with **Laravel 11** and secured with **Laravel Sanctum** for token-based authentication and an **X-API-KEY** header for global API access control.

---

## Table of Contents

- [Requirements](#requirements)
- [Setup](#setup)
- [Authentication Overview](#authentication-overview)
- [Endpoints](#endpoints)
  - [Auth](#auth)
  - [Tickets](#tickets)

---

## Requirements

- PHP 8.2+
- Composer
- SQLite (default) or any Laravel-supported database

---

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Copy environment file and generate app key
cp .env.example .env
php artisan key:generate

# 3. Run migrations
php artisan migrate

# 4. Start the development server
php artisan serve
```

> The API will be available at `http://localhost:8000/api`.

---

## Authentication Overview

The API uses **two layers of security**:

### 1. X-API-KEY (Global)
Every request must include the API key in the header:

```
X-API-KEY: <your_api_key>
```

The key is defined in your `.env` file as `API_KEY`.

### 2. Bearer Token (Sanctum)
Some routes require a logged-in user. After a successful login, include the returned token in the `Authorization` header:

```
Authorization: Bearer <token>
```

---

## Endpoints

### Auth

#### `POST /api/register`
Register a new user.

**Headers:**
```
X-API-KEY: <your_api_key>
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "João Silva",
  "login": "joao.silva",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

**Response `201 Created`:**
```json
{
  "status": "success",
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "João Silva",
      "login": "joao.silva",
      "created_at": "2026-03-11T13:00:00.000000Z",
      "updated_at": "2026-03-11T13:00:00.000000Z"
    }
  }
}
```

---

#### `POST /api/login`
Authenticate a user and obtain a Bearer token.

**Headers:**
```
X-API-KEY: <your_api_key>
Content-Type: application/json
```

**Request Body:**
```json
{
  "login": "joao.silva",
  "password": "secret123"
}
```

**Response `200 OK`:**
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "access_token": "1|abcdefghijklmnopqrstuvwxyz123456",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "João Silva",
      "login": "joao.silva"
    }
  }
}
```

**Response `401 Unauthorized`** (wrong credentials):
```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```

---

### Tickets

Tickets represent attendance passwords (senhas de atendimento) generated for patients at the totem.

#### Ticket Object

| Field          | Type      | Description                                              |
|----------------|-----------|----------------------------------------------------------|
| `id`           | integer   | Auto-incremented primary key                            |
| `key`          | string    | Unique ticket key, e.g. `N-A3K9`                        |
| `service_type` | string    | Type of service (see options below)                     |
| `completed`    | boolean   | Whether attendance has been completed (`false` default) |
| `guiche`       | string    | Name of the attendant who called the ticket (nullable)  |
| `called_at`    | timestamp | When the ticket was called to a counter (nullable)      |
| `created_at`   | timestamp | When the ticket was generated (used as wait-time base)  |

#### Key Format

The ticket key is composed of a **prefix** + **4 random uppercase alphanumeric characters**:

| Service Type                | Prefix | Example  |
|-----------------------------|--------|----------|
| Atendimento Normal          | `N`    | `N-B7XQ` |
| Atendimento Preferencial    | `P`    | `P-A1K2` |
| Entrega de Exames           | `E`    | `E-C9MZ` |
| Recebimento de Amostras     | `R`    | `R-T4WP` |

---

#### `GET /api/tickets`
Returns all **pending** tickets — not yet completed and not yet called — ordered by priority:

1. **Atendimento Preferencial** tickets first (oldest first within the group)
2. All other types (oldest first within the group)

**Headers:**
```
X-API-KEY: <your_api_key>
```

**Response `200 OK`:**
```json
[
  {
    "id": 3,
    "key": "P-A1K2",
    "service_type": "Atendimento Preferencial",
    "completed": false,
    "guiche": null,
    "called_at": null,
    "created_at": "2026-03-11T10:01:00.000000Z"
  },
  {
    "id": 1,
    "key": "N-B7XQ",
    "service_type": "Atendimento Normal",
    "completed": false,
    "guiche": null,
    "called_at": null,
    "created_at": "2026-03-11T09:58:00.000000Z"
  }
]
```

---

#### `POST /api/tickets`
Generate a new attendance ticket.

**Headers:**
```
X-API-KEY: <your_api_key>
Content-Type: application/json
```

**Request Body:**
```json
{
  "service_type": "Atendimento Normal"
}
```

> **Valid values for `service_type`:**
> - `Atendimento Normal`
> - `Atendimento Preferencial`
> - `Entrega de Exames`
> - `Recebimento de Amostras`

**Response `201 Created`:**
```json
{
  "id": 5,
  "key": "N-B7XQ",
  "service_type": "Atendimento Normal",
  "completed": false,
  "guiche": null,
  "called_at": null,
  "created_at": "2026-03-11T10:05:00.000000Z",
  "updated_at": "2026-03-11T10:05:00.000000Z"
}
```

**Response `422 Unprocessable Entity`** (invalid service type):
```json
{
  "message": "The selected service_type is invalid.",
  "errors": {
    "service_type": ["The selected service_type is invalid."]
  }
}
```

---

#### `POST /api/tickets/{id}/call`
Call a ticket to an attendance counter (guichê). The **guichê name is taken from the authenticated user** — no payload body needed.

> 🔒 **Requires Bearer Token** (Sanctum authentication)

**Headers:**
```
X-API-KEY: <your_api_key>
Authorization: Bearer <token>
```

**URL Parameters:**
- `{id}` — Can be the numeric `id` (e.g. `5`) **or** the ticket `key` (e.g. `N-B7XQ`)

**Response `200 OK`:**
```json
{
  "id": 5,
  "key": "N-B7XQ",
  "service_type": "Atendimento Normal",
  "completed": false,
  "guiche": "João Silva",
  "called_at": "2026-03-11T10:10:00.000000Z",
  "created_at": "2026-03-11T10:05:00.000000Z",
  "updated_at": "2026-03-11T10:10:00.000000Z"
}
```

**Response `401 Unauthorized`** (missing or invalid token):
```json
{
  "message": "Unauthenticated."
}
```

**Response `404 Not Found`** (ticket does not exist):
```json
{
  "message": "No query results for model [App\\Models\\Ticket]."
}
```

**Response `422 Unprocessable Entity`** (ticket already called):
```json
{
  "message": "Este ticket já foi chamado.",
  "ticket": { ... }
}
```

---


#### `GET /api/tickets/recently-called`
Returns the **last 5 called tickets** (tickets that have already been called to a counter), in descending order of call time. Includes all ticket information, such as counter (guiche), call time, etc.

**Headers:**
```
X-API-KEY: <your_api_key>
```

**Response `200 OK`:**
```json
[
  {
    "id": 8,
    "key": "P-1A2B",
    "service_type": "Atendimento Preferencial",
    "completed": false,
    "guiche": "João Silva",
    "called_at": "2026-03-11T13:20:00.000000Z",
    "created_at": "2026-03-11T13:10:00.000000Z",
    "updated_at": "2026-03-11T13:20:00.000000Z"
  },
  {
    "id": 7,
    "key": "N-9Z8Y",
    "service_type": "Atendimento Normal",
    "completed": false,
    "guiche": "Maria Souza",
    "called_at": "2026-03-11T13:18:00.000000Z",
    "created_at": "2026-03-11T13:05:00.000000Z",
    "updated_at": "2026-03-11T13:18:00.000000Z"
  }
  // ...up to 5 tickets
]
```

---
Mark a ticket as completed after attendance is finished.

**Headers:**
```
X-API-KEY: <your_api_key>
```

**URL Parameters:**
- `{id}` — Can be the numeric `id` or the ticket `key`

**Response `200 OK`:**
```json
{
  "id": 5,
  "key": "N-B7XQ",
  "service_type": "Atendimento Normal",
  "completed": true,
  "guiche": "João Silva",
  "called_at": "2026-03-11T10:10:00.000000Z",
  "created_at": "2026-03-11T10:05:00.000000Z",
  "updated_at": "2026-03-11T10:15:00.000000Z"
}
```

---

## Typical Ticket Lifecycle

```
[Totem] POST /tickets           → Ticket generated (N-B7XQ)
           ↓
[Counter] POST /tickets/N-B7XQ/call  → Ticket called (guiche = "João Silva")
           ↓
[Counter] PATCH /tickets/N-B7XQ/complete → Attendance completed
```

---

## License

Proprietary — Unilab.
