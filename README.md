# B-Unilab API

REST API for the Unilab totem/attendance system.

Stack:
- Laravel 12
- Laravel Sanctum (token auth)
- Global API Key middleware (`X-API-KEY`)

## Table of Contents
- Requirements
- Setup
- Security Model
- Default Admin Account
- Data Models
- Endpoints
- Error Reference
- Operational Notes

## Requirements
- PHP 8.2+
- Composer
- Database supported by Laravel (SQLite, MySQL, PostgreSQL, etc.)

## Setup
```bash
# Install dependencies
composer install

# Create env file
cp .env.example .env

# Generate app key
php artisan key:generate

# Configure database and APP_API_KEY in .env, then run migrations
php artisan migrate

# Optional but recommended for video URL serving
php artisan storage:link

# Start API
php artisan serve
```

Base URL (local):
`http://localhost:8000/api`

## Security Model

All API routes require:
- `X-API-KEY` header

Some routes also require:
- `Authorization: Bearer <sanctum_token>`

Admin routes require:
- Valid Sanctum token
- Authenticated user with `is_admin = true`

### Required headers
```http
X-API-KEY: <APP_API_KEY>
Accept: application/json
Content-Type: application/json
```

For authenticated routes:
```http
Authorization: Bearer <token>
```

## Default Admin Account

After running migrations, the API guarantees a default admin account:
- `login`: `admin`
- `password`: `admin`
- `is_admin`: `true`

Important: change this password immediately in production.

## Data Models

### User fields used by API
- `id` (int)
- `uuid` (uuid)
- `name` (string)
- `login` (string, unique)
- `password` (hashed)
- `active` (boolean)
- `is_admin` (boolean)
- `created_at`, `updated_at` (timestamp)

### Ticket fields used by API
- `id` (int)
- `key` (string, unique, e.g. `P-0001`)
- `service_type` (string)
- `completed` (boolean)
- `guiche` (nullable string)
- `called_at` (nullable timestamp)
- `completed_at` (nullable timestamp)
- `created_at`, `updated_at` (timestamp)

## Endpoints

Legend:
- Public: requires only `X-API-KEY`
- Auth: requires `X-API-KEY` + Bearer token
- Admin: requires `X-API-KEY` + Bearer token + admin user

### Auth

#### POST `/api/register` (Public)
Create a non-admin user.

Request body:
```json
{
  "name": "Maria Silva",
  "login": "maria.silva",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

Validation:
- `name`: required, string, max 255
- `login`: required, string, max 100, unique in users
- `password`: required, string, min 8, confirmed

Success response `201`:
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
      "created_at": "2026-03-12T12:00:00.000000Z",
      "updated_at": "2026-03-12T12:00:00.000000Z"
    }
  }
}
```

Common errors:
- `422` validation error
- `401` missing/invalid `X-API-KEY`

#### POST `/api/login` (Public)
Authenticate user and return Sanctum token.

Request body:
```json
{
  "login": "maria.silva",
  "password": "secret123"
}
```

Validation:
- `login`: required, string, max 255
- `password`: required, string

Success response `200`:
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
      "created_at": "2026-03-12T12:00:00.000000Z",
      "updated_at": "2026-03-12T12:00:00.000000Z"
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

### Tickets

Allowed `service_type` values:
- `Atendimento Normal`
- `Atendimento Preferencial`
- `Recebimento de Exames ou Entrega de Amostras`

Generated key prefixes:
- Normal: `N`
- Preferencial: `P`
- Recebimento de Exames ou Entrega de Amostras: `E`

#### GET `/api/tickets` (Public)
List pending tickets (`completed = false` and `called_at = null`), with priority tickets first.

Success response `200`:
```json
[
  {
    "id": 1,
    "key": "P-1A2B",
    "service_type": "Atendimento Preferencial",
    "completed": false,
    "guiche": null,
    "called_at": null,
    "completed_at": null,
    "created_at": "2026-03-12T11:00:00.000000Z",
    "updated_at": "2026-03-12T11:00:00.000000Z"
  }
]
```

#### POST `/api/tickets` (Public)
Create a ticket.

Request body:
```json
{
  "service_type": "Atendimento Normal"
}
```

Success response `201`:
```json
{
  "id": 2,
  "key": "N-Z9X1",
  "service_type": "Atendimento Normal",
  "completed": false,
  "guiche": null,
  "called_at": null,
  "completed_at": null,
  "created_at": "2026-03-12T11:05:00.000000Z",
  "updated_at": "2026-03-12T11:05:00.000000Z"
}
```

Validation error `422` (example):
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
Call a ticket. `{id}` accepts numeric id or ticket key.
`guiche` is automatically set from authenticated user name.

Success response `200`:
```json
{
  "id": 2,
  "key": "N-Z9X1",
  "service_type": "Atendimento Normal",
  "completed": false,
  "guiche": "Guiche 01",
  "called_at": "2026-03-12T11:10:00.000000Z",
  "completed_at": null,
  "created_at": "2026-03-12T11:05:00.000000Z",
  "updated_at": "2026-03-12T11:10:00.000000Z"
}
```

Already called `422`:
```json
{
  "message": "Este ticket já foi chamado.",
  "ticket": {
    "id": 2,
    "key": "N-Z9X1"
  }
}
```

#### POST `/api/tickets/{id}/recall` (Auth)
Recall a ticket that was already called. `{id}` accepts numeric id or ticket key.
This updates `called_at` to the current time so the ticket can be announced again.

Success response `200`:
```json
{
  "id": 2,
  "key": "N-Z9X1",
  "service_type": "Atendimento Normal",
  "completed": false,
  "guiche": "Guiche 01",
  "called_at": "2026-03-12T11:14:00.000000Z",
  "completed_at": null,
  "created_at": "2026-03-12T11:05:00.000000Z",
  "updated_at": "2026-03-12T11:14:00.000000Z"
}
```

Validation `422` examples:
```json
{
  "message": "Este ticket ainda nao foi chamado."
}
```

```json
{
  "message": "Este ticket ja foi finalizado."
}
```

#### PATCH `/api/tickets/{id}/complete` (Public)
Mark ticket as completed. `{id}` accepts numeric id or ticket key.

Success response `200`:
```json
{
  "id": 2,
  "key": "N-Z9X1",
  "service_type": "Atendimento Normal",
  "completed": true,
  "guiche": "Guiche 01",
  "called_at": "2026-03-12T11:10:00.000000Z",
  "completed_at": "2026-03-12T11:18:00.000000Z",
  "created_at": "2026-03-12T11:05:00.000000Z",
  "updated_at": "2026-03-12T11:18:00.000000Z"
}
```

#### PATCH `/api/tickets/{id}/cancel` (Public)
Mark ticket as canceled. `{id}` accepts numeric id or ticket key.
This endpoint updates the same completion fields used by `/complete`.

Success response `200`:
```json
{
  "id": 2,
  "key": "N-Z9X1",
  "service_type": "Atendimento Normal",
  "completed": true,
  "guiche": "Guiche 01",
  "called_at": "2026-03-12T11:10:00.000000Z",
  "completed_at": "2026-03-12T11:20:00.000000Z",
  "created_at": "2026-03-12T11:05:00.000000Z",
  "updated_at": "2026-03-12T11:20:00.000000Z"
}
```

#### GET `/api/tickets/completed` (Public)
List tickets completed today (from `completed_at` or fallback `updated_at`).

Success response `200`:
```json
[
  {
    "id": 2,
    "key": "N-Z9X1",
    "service_type": "Atendimento Normal",
    "completed": true,
    "guiche": "Guiche 01",
    "called_at": "2026-03-12T11:10:00.000000Z",
    "completed_at": "2026-03-12T11:18:00.000000Z",
    "created_at": "2026-03-12T11:05:00.000000Z",
    "updated_at": "2026-03-12T11:18:00.000000Z"
  }
]
```

#### GET `/api/tickets/recently-called` (Public)
Return up to 5 most recently called tickets.

Success response `200`:
```json
[
  {
    "id": 2,
    "key": "N-Z9X1",
    "service_type": "Atendimento Normal",
    "completed": true,
    "guiche": "Guiche 01",
    "called_at": "2026-03-12T11:10:00.000000Z",
    "completed_at": "2026-03-12T11:18:00.000000Z",
    "created_at": "2026-03-12T11:05:00.000000Z",
    "updated_at": "2026-03-12T11:18:00.000000Z"
  }
]
```

### Reports

#### GET `/api/reports/attendances` (Admin)
Generate attendance metrics for a date range. Uses both active tickets and archived tickets.

Query params:
- `start_date` (required, `Y-m-d`)
- `end_date` (required, `Y-m-d`, must be `>= start_date`)

Example:
`/api/reports/attendances?start_date=2026-03-01&end_date=2026-03-12`

Success response `200`:
```json
{
  "period": {
    "start_date": "2026-03-01",
    "end_date": "2026-03-12",
    "days": 12
  },
  "average_wait_time": {
    "seconds": 325,
    "formatted": "00:05:25"
  },
  "average_attendances_per_day": 48.25,
  "attendances_per_day": {
    "2026-03-10": 44,
    "2026-03-11": 51
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

### Videos

#### GET `/api/videos` (Public)
List available videos.

Success response `200`:
```json
[
  {
    "filename": "video_abcd1234efgh5678.mp4",
    "url": "http://localhost:8000/storage/videos/video_abcd1234efgh5678.mp4"
  }
]
```

#### GET `/api/videos/{filename}` (Public)
Stream a specific video file.

Success response `200`:
- Content-Type: `video/mp4`

Not found `404`:
```json
{
  "message": "Video not found"
}
```

#### POST `/api/videos/upload` (Admin)
Upload MP4 video to public storage.

Request (multipart/form-data):
- `video` (required, MIME `video/mp4`)

Success response `201`:
```json
{
  "message": "Video uploaded successfully",
  "filename": "video_abcd1234efgh5678.mp4",
  "url": "http://localhost:8000/storage/videos/video_abcd1234efgh5678.mp4"
}
```

#### DELETE `/api/videos/{filename}` (Admin)
Delete video from public storage.

Success response `200`:
```json
{
  "message": "Video deleted successfully"
}
```

Not found `404`:
```json
{
  "message": "Video not found"
}
```

### User Management (Admin)

All user management routes are admin-only.

#### GET `/api/users`
List users (sorted by name).

Success response `200`:
```json
[
  {
    "id": 1,
    "uuid": "dc7f5da5-4b94-48f9-b488-e124412a3447",
    "name": "Administrador",
    "login": "admin",
    "active": true,
    "is_admin": true,
    "created_at": "2026-03-12T11:08:06.000000Z",
    "updated_at": "2026-03-12T11:08:06.000000Z"
  }
]
```

#### PATCH `/api/users/{user}`
Update user fields. Route parameter `{user}` is user id (route model binding).

Request body (all fields optional):
```json
{
  "name": "Guiche 02",
  "login": "guiche_02",
  "password": "newsecret123",
  "password_confirmation": "newsecret123",
  "active": true
}
```

Validation:
- `name`: sometimes, string, max 255
- `login`: sometimes, string, max 100, unique except current user
- `password`: sometimes, string, min 8, confirmed
- `active`: sometimes, boolean

Success response `200`:
```json
{
  "message": "User updated successfully",
  "user": {
    "id": 2,
    "uuid": "95ef5071-0399-483d-bec5-ed83f3acbeb6",
    "name": "Guiche 02",
    "login": "guiche_02",
    "active": true,
    "is_admin": false,
    "created_at": "2026-03-12T11:11:42.000000Z",
    "updated_at": "2026-03-12T11:26:00.000000Z"
  }
}
```

#### DELETE `/api/users/{user}`
Delete user.

Success response `200`:
```json
{
  "message": "User deleted successfully"
}
```

Business rule error `422` (cannot remove last admin):
```json
{
  "message": "Cannot delete the last administrator"
}
```

#### PATCH `/api/users/{user}/make-admin`
Promote user to admin.

Success response `200`:
```json
{
  "message": "User promoted to administrator successfully",
  "user": {
    "id": 2,
    "is_admin": true
  }
}
```

If already admin `200`:
```json
{
  "message": "User is already an administrator",
  "user": {
    "id": 2,
    "is_admin": true
  }
}
```

#### PATCH `/api/users/{user}/remove-admin`
Remove admin role from user.

Success response `200`:
```json
{
  "message": "Administrator access removed successfully",
  "user": {
    "id": 2,
    "is_admin": false
  }
}
```

If user is not admin `200`:
```json
{
  "message": "User is not an administrator",
  "user": {
    "id": 2,
    "is_admin": false
  }
}
```

Business rule error `422` (cannot demote last admin):
```json
{
  "message": "Cannot remove administrator access from the last administrator"
}
```

## Error Reference

### `401 Unauthorized`
- Missing/invalid `X-API-KEY`:
```json
{
  "message": "Unauthorized: Invalid or missing API Key"
}
```
- Missing/invalid Sanctum token on auth/admin routes:
```json
{
  "message": "Unauthenticated."
}
```

### `403 Forbidden`
On admin routes when authenticated user is not admin:
```json
{
  "message": "Forbidden: administrator access required"
}
```

### `404 Not Found`
- Missing resource (examples):
```json
{
  "message": "Video not found"
}
```

### `422 Unprocessable Entity`
- Validation errors (Laravel default format)
- Business rules (examples):
```json
{
  "message": "Cannot delete the last administrator"
}
```

## Operational Notes

- Completed tickets from previous days are archived daily at `00:05` by the scheduled command `tickets:archive-completed`.
- Attendance reports merge data from current tickets and archived tickets.
- API errors are always rendered as JSON for `/api/*` routes.

## License

Proprietary - Unilab.
