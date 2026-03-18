# B-Unilab API

REST API for a queue/totem attendance system.

## Tech Stack

- Laravel 12
- Laravel Sanctum (Bearer token authentication)
- Global API key middleware (`X-API-KEY`) for all `/api/*` routes

## Requirements

- PHP 8.2+
- Composer
- A Laravel-supported database (SQLite, MySQL, PostgreSQL, etc.)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Configure DB and APP_API_KEY in .env
php artisan migrate

# Optional, but recommended for video public URLs
php artisan storage:link

php artisan serve
```

Local base URL:

```text
http://localhost:8000/api
```

## Ticket Printing over SMB

The backend uses `mike42/escpos-php` with `WindowsPrintConnector`, which also works on Linux for shared SMB printers.

Example `.env` for Ubuntu:

```dotenv
TICKET_PRINTER_ENABLED=true
TICKET_PRINTER_CONNECTOR="smb://PRINT-SERVER/EPSON-TM-T20X"
TICKET_PRINTER_USERNAME=print-user
TICKET_PRINTER_PASSWORD=print-secret
TICKET_PRINTER_PROFILE=simple
TICKET_PRINTER_HEADER="SENHA DE ATENDIMENTO"
```

Notes:

- If `TICKET_PRINTER_CONNECTOR` already contains credentials, the backend keeps that value unchanged.
- If `TICKET_PRINTER_USERNAME` is set, the backend injects `username[:password]` into the SMB URL before printing.
- On Ubuntu, the server must have Samba client tools available so the SMB print command can run.
- After changing printer variables in production, run `php artisan config:clear` or restart PHP-FPM/queue workers if configuration is cached.

## Authentication and Authorization

Every API request must include:

```http
X-API-KEY: <APP_API_KEY>
Accept: application/json
```

For authenticated routes, also include:

```http
Authorization: Bearer <sanctum_token>
```

Access levels:

- Public: requires only `X-API-KEY`
- Auth: requires `X-API-KEY` and a valid Bearer token
- Admin: requires `X-API-KEY`, valid Bearer token, and `is_admin = true`

## Default Admin User

After migrations, the project ensures a default admin user:

- `login`: `admin`
- `password`: `admin`
- `is_admin`: `true`

Change this password immediately in production.

## Ticket Business Rules

Allowed `service_type` values:

- `Atendimento Normal`
- `Atendimento Preferencial`
- `Retirada de Exames ou Entrega de Amostras`

Generated ticket key prefix by service type:

- `N` for `Atendimento Normal`
- `P` for `Atendimento Preferencial`
- `E` for `Retirada de Exames ou Entrega de Amostras`

Outcome types used by reports:

- `completed`
- `canceled`

## Endpoint Summary

| Method | Path | Access |
|---|---|---|
| POST | `/api/register` | Public |
| POST | `/api/login` | Public |
| GET | `/api/tickets` | Public |
| POST | `/api/tickets` | Public |
| GET | `/api/tickets/recently-called` | Public |
| GET | `/api/videos` | Public |
| GET | `/api/videos/{filename}` | Public |
| POST | `/api/tickets/{id}/call` | Auth |
| POST | `/api/tickets/{id}/recall` | Auth |
| PATCH | `/api/tickets/{id}/complete` | Auth |
| PATCH | `/api/tickets/{id}/cancel` | Auth |
| GET | `/api/tickets/completed` | Auth |
| GET | `/api/reports/attendances` | Admin |
| POST | `/api/videos/upload` | Admin |
| DELETE | `/api/videos/{filename}` | Admin |
| GET | `/api/users` | Admin |
| PATCH | `/api/users/{user}` | Admin |
| DELETE | `/api/users/{user}` | Admin |
| PATCH | `/api/users/{user}/make-admin` | Admin |
| PATCH | `/api/users/{user}/remove-admin` | Admin |

## Detailed Endpoints

### 1) Auth

#### POST `/api/register` (Public)

Creates a non-admin user.

Request body:

```json
{
  "name": "Maria Silva",
  "login": "maria.silva",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

Validation rules:

- `name`: required, string, max 255
- `login`: required, string, max 100, unique in `users.login`
- `password`: required, string, min 8, confirmed

Success `201`:

```json
{
  "status": "success",
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 10,
      "uuid": "dd43f5c8-b370-4f67-9ec6-85ba77f86659",
      "name": "Maria Silva",
      "login": "maria.silva",
      "active": true,
      "is_admin": false,
      "created_at": "2026-03-13T12:00:00.000000Z",
      "updated_at": "2026-03-13T12:00:00.000000Z"
    }
  }
}
```

#### POST `/api/login` (Public)

Authenticates user and returns a Sanctum token.

Request body:

```json
{
  "login": "maria.silva",
  "password": "secret123"
}
```

Validation rules:

- `login`: required, string, max 255
- `password`: required, string

Success `200`:

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "access_token": "1|token-value",
    "token_type": "Bearer",
    "user": {
      "id": 10,
      "uuid": "dd43f5c8-b370-4f67-9ec6-85ba77f86659",
      "name": "Maria Silva",
      "login": "maria.silva",
      "active": true,
      "is_admin": false,
      "created_at": "2026-03-13T12:00:00.000000Z",
      "updated_at": "2026-03-13T12:00:00.000000Z"
    }
  }
}
```

Invalid credentials `401`:

```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```

### 2) Tickets

Note: in `/api/tickets/{id}/...`, the `{id}` can be either numeric ticket ID or ticket key (for example `P-0001`).

#### GET `/api/tickets` (Public)

Returns waiting tickets (`completed = false` and `called_at = null`).
Priority service appears first.

Success `200` example:

```json
[
  {
    "id": 1,
    "key": "P-0001",
    "service_type": "Atendimento Preferencial",
    "completed": false,
    "guiche": null,
    "called_at": null,
    "completed_at": null,
    "completion_type": null,
    "created_at": "2026-03-13T10:00:00.000000Z",
    "updated_at": "2026-03-13T10:00:00.000000Z"
  }
]
```

#### POST `/api/tickets` (Public)

Creates a new ticket and tries to print it.

Request body:

```json
{
  "service_type": "Atendimento Normal"
}
```

Validation rules:

- `service_type`: required, string, one of the allowed service values

Success `201`:

```json
{
  "ticket": {
    "id": 2,
    "key": "N-0001",
    "service_type": "Atendimento Normal",
    "completed": false,
    "guiche": null,
    "called_at": null,
    "completed_at": null,
    "completion_type": null,
    "created_at": "2026-03-13T10:05:00.000000Z",
    "updated_at": "2026-03-13T10:05:00.000000Z"
  },
  "print": {
    "status": "sucesso"
  }
}
```

Success with print failure still returns `201`:

```json
{
  "ticket": {
    "id": 2,
    "key": "N-0001"
  },
  "print": {
    "status": "erro",
    "message": "Ticket gerado, mas nao foi possivel imprimir automaticamente.",
    "error": "..."
  }
}
```

Validation error `422` example:

```json
{
  "message": "The selected service_type is invalid.",
  "errors": {
    "service_type": [
      "The selected service_type is invalid."
    ]
  }
}
```

#### POST `/api/tickets/{id}/call` (Auth)

Sets `called_at` to now and `guiche` from the authenticated user name.

Request body: none

Success `200`: returns updated ticket.

If already called, returns `422`.

#### POST `/api/tickets/{id}/recall` (Auth)

Recalls a ticket by updating `called_at` to now and `guiche` from authenticated user.

Request body: none

Success `200`: returns updated ticket.

Common `422` cases:

- Ticket has not been called yet
- Ticket is already completed

#### PATCH `/api/tickets/{id}/complete` (Auth)

Marks the ticket as completed.

Side effects:

- `completed = true`
- `completed_at = now`
- `completion_type = completed`

Request body: none

Success `200`: returns updated ticket.

#### PATCH `/api/tickets/{id}/cancel` (Auth)

Marks the ticket as canceled.

Side effects:

- `completed = true`
- `completed_at = now`
- `completion_type = canceled`

Request body: none

Success `200`: returns updated ticket.

#### GET `/api/tickets/completed` (Auth)

Returns tickets completed today. Uses `completed_at`, and falls back to `updated_at` when needed.

Success `200`: array of tickets.

#### GET `/api/tickets/recently-called` (Public)

Returns up to 5 most recently called tickets.

Success `200`: array of tickets.

### 3) Reports

#### GET `/api/reports/attendances` (Admin)

Returns attendance KPIs for a date range, combining active and archived data.

Query params:

- `start_date` required, format `Y-m-d`
- `end_date` required, format `Y-m-d`, must be equal or after `start_date`

Example:

```text
/api/reports/attendances?start_date=2026-03-01&end_date=2026-03-13
```

Success `200`:

```json
{
  "period": {
    "start_date": "2026-03-01",
    "end_date": "2026-03-13",
    "days": 13
  },
  "average_wait_time": {
    "seconds": 325,
    "formatted": "00:05:25"
  },
  "average_attendances_per_day": 48.25,
  "attendances_per_day": {
    "2026-03-12": 44,
    "2026-03-13": 51
  },
  "attendances_by_type": {
    "priority": 27,
    "others": 552
  },
  "attendances_by_outcome": {
    "completed": 540,
    "canceled": 39,
    "unknown": 0
  },
  "total_attendances": 579
}
```

### 4) Videos

#### GET `/api/videos` (Public)

Lists uploaded MP4 files.

Success `200`:

```json
[
  {
    "filename": "video_abcd1234efgh5678.mp4",
    "url": "http://localhost:8000/storage/videos/video_abcd1234efgh5678.mp4"
  }
]
```

#### GET `/api/videos/{filename}` (Public)

Streams video file (`Content-Type: video/mp4`).

If file does not exist, returns `404`:

```json
{
  "message": "Video not found"
}
```

#### POST `/api/videos/upload` (Admin)

Uploads an MP4 video.

Request type: `multipart/form-data`

Fields:

- `video`: required file, MIME must be `video/mp4`

Success `201`:

```json
{
  "message": "Video uploaded successfully",
  "filename": "video_abcd1234efgh5678.mp4",
  "url": "http://localhost:8000/storage/videos/video_abcd1234efgh5678.mp4"
}
```

#### DELETE `/api/videos/{filename}` (Admin)

Deletes a video by filename.

Success `200`:

```json
{
  "message": "Video deleted successfully"
}
```

If file does not exist, returns `404`:

```json
{
  "message": "Video not found"
}
```

### 5) User Management (Admin)

#### GET `/api/users` (Admin)

Returns all users sorted by name.

Success `200`: array of users with these fields:

- `id`
- `uuid`
- `name`
- `login`
- `active`
- `is_admin`
- `created_at`
- `updated_at`

#### PATCH `/api/users/{user}` (Admin)

Updates one user. `{user}` is the user ID (route model binding).

Request body (all optional):

```json
{
  "name": "Desk 02",
  "login": "desk_02",
  "password": "newsecret123",
  "password_confirmation": "newsecret123",
  "active": true
}
```

Validation rules:

- `name`: sometimes, string, max 255
- `login`: sometimes, string, max 100, unique except current user
- `password`: sometimes, string, min 8, confirmed
- `active`: sometimes, boolean

Success `200`:

```json
{
  "message": "User updated successfully",
  "user": {
    "id": 2,
    "uuid": "95ef5071-0399-483d-bec5-ed83f3acbeb6",
    "name": "Desk 02",
    "login": "desk_02",
    "active": true,
    "is_admin": false,
    "created_at": "2026-03-13T11:11:42.000000Z",
    "updated_at": "2026-03-13T11:26:00.000000Z"
  }
}
```

#### DELETE `/api/users/{user}` (Admin)

Deletes a user.

Success `200`:

```json
{
  "message": "User deleted successfully"
}
```

Business rule `422` when trying to delete the last admin:

```json
{
  "message": "Cannot delete the last administrator"
}
```

#### PATCH `/api/users/{user}/make-admin` (Admin)

Promotes user to admin.

Success `200`:

```json
{
  "message": "User promoted to administrator successfully",
  "user": {
    "id": 2,
    "uuid": "95ef5071-0399-483d-bec5-ed83f3acbeb6",
    "name": "Desk 02",
    "login": "desk_02",
    "active": true,
    "is_admin": true,
    "created_at": "2026-03-13T11:11:42.000000Z",
    "updated_at": "2026-03-13T11:26:00.000000Z"
  }
}
```

If already admin, still returns `200` with current user data and message.

#### PATCH `/api/users/{user}/remove-admin` (Admin)

Removes admin role.

Success `200`:

```json
{
  "message": "Administrator access removed successfully",
  "user": {
    "id": 2,
    "uuid": "95ef5071-0399-483d-bec5-ed83f3acbeb6",
    "name": "Desk 02",
    "login": "desk_02",
    "active": true,
    "is_admin": false,
    "created_at": "2026-03-13T11:11:42.000000Z",
    "updated_at": "2026-03-13T11:26:00.000000Z"
  }
}
```

If user is not admin, returns `200` with a message.

Business rule `422` when trying to remove admin from the last admin user:

```json
{
  "message": "Cannot remove administrator access from the last administrator"
}
```

## Common Error Responses

### 401 Unauthorized

Missing or invalid API key:

```json
{
  "message": "Unauthorized: Invalid or missing API Key"
}
```

Missing or invalid Bearer token on Auth/Admin routes:

```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden

Authenticated user is not admin on Admin routes:

```json
{
  "message": "Forbidden: administrator access required"
}
```

### 404 Not Found

Example:

```json
{
  "message": "Video not found"
}
```

### 422 Unprocessable Entity

Used for validation errors and business rule violations.

Example:

```json
{
  "message": "Cannot delete the last administrator"
}
```

## Operational Notes

- Completed tickets from previous days are archived daily at `00:05` by scheduled command `tickets:archive-completed`.
- Attendance reports merge current tickets and archived tickets.
- API errors for `/api/*` are always returned as JSON.

## License

Proprietary - Unilab.
