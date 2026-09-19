# Attendance API

Laravel 8 API (PHP ^7.3, tested on 7.3.28) backing the mobile face-recognition attendance app. Connects directly to the existing `rconnectnew` MySQL database — `student`, `batch`, and `attendance` are used as-is (no schema changes); the only new table is `student_face_embeddings`.

## Setup

1. `composer install`
2. Copy `.env.example` to `.env` and fill in:
   - `DB_DATABASE=rconnectnew`, `DB_USERNAME`, `DB_PASSWORD` for your MySQL instance
   - `APP_TENANT_ID` / `APP_LOCATION_ID` — the single tenant/location this deployment serves (every query is scoped to these server-side; they are never read from the request)
   - `API_KEY` — a random secret (e.g. `php -r "echo bin2hex(random_bytes(32));"`) that callers must send back in the `X-API-Key` header
3. `php artisan key:generate` (if `APP_KEY` isn't already set)
4. `php artisan migrate` — creates only `student_face_embeddings`

## Authentication

Every `/api/v1/*` route requires an `X-API-Key` header matching `API_KEY` in `.env`. Missing or wrong key → `401`.

## Response envelope

```json
// success
{ "success": true, "data": ..., "message": "optional" }

// error
{ "success": false, "message": "...", "errors": { "field": ["..."] } }
```

## Endpoints

### `GET /api/v1/students/today`
Active students (`status = ACTIVE`, not deleted) whose `batch` has a session scheduled today, i.e. today's day-of-week column (`monday`…`sunday`) is non-empty, for the configured tenant/location.

**Response `200`**
```json
{
  "success": true,
  "data": [
    {
      "id": 3119,
      "registrationNo": "RNR-2024-03-3119",
      "firstName": "Aadidev",
      "middleName": null,
      "lastName": "Bhowmik",
      "fullName": "Aadidev Bhowmik",
      "status": "ACTIVE",
      "sessionTime": "5:30 PM"
    }
  ]
}
```

### `POST /api/v1/students/{studentId}/embeddings`
Stores a face embedding captured/generated on-device for a student.

**Request**
```json
{ "embedding": [0.123, -0.045, ...], "sampleLabel": "front", "createdBy": "device-uuid" }
```
`embedding`: required, array of numbers. `sampleLabel`: optional, max 20 chars (e.g. `front`/`left`/`right`). `createdBy`: optional, defaults to `"mobile-app"`.

**Response `201`**
```json
{
  "success": true,
  "message": "Embedding stored.",
  "data": { "id": 1, "studentId": 3119, "sampleLabel": "front", "embedding": [0.123, -0.045], "createdAt": "2026-09-02T14:41:54+00:00" }
}
```
`404` if the student doesn't exist / isn't active for the configured tenant/location. `422` on validation failure.

### `GET /api/v1/students/{studentId}/embeddings`
All stored embeddings for one student.

**Response `200`** — same shape as the `data` object above, as an array.

### `GET /api/v1/embeddings/today`
Bulk convenience endpoint: every embedding for every student on today's roster, keyed by `studentId` — lets a client fetch everything needed for on-device face matching in one call.

**Response `200`**
```json
{ "success": true, "data": { "3119": [ { "id": 1, "studentId": 3119, "sampleLabel": "front", "embedding": [...], "createdAt": "..." } ] } }
```

### `POST /api/v1/attendance`
Marks attendance for a student, today. `sessionDate` is always the server's current date; `sessionTime` is stored as a separate column. **Idempotent per day** — calling this again for the same student on the same day returns the existing record (`200`) instead of creating a duplicate.

**Request**
```json
{ "studentId": 3119, "sessionTime": "14:32:10", "createdBy": "device-uuid" }
```
`studentId`: required. `sessionTime`: optional, `H:i:s` 24-hour format, defaults to current server time. `createdBy`: optional, defaults to `"mobile-app"`.

**Response** `201` (newly created) or `200` (already marked today) —
```json
{
  "success": true,
  "message": "Attendance marked.",
  "data": { "id": 253594, "studentId": 3119, "sessionDate": "2026-09-02", "sessionTime": "14:32:10", "createdBy": "device-uuid", "createdAt": "2026-09-02T14:32:10+00:00" }
}
```
`404` if the student doesn't exist / isn't active for the configured tenant/location. `422` on validation failure.

## Notes / assumptions

- "Scheduled today" only checks that today's `batch` day-column is non-empty — it does not filter by proximity to the current time.
- Attendance marking is idempotent per calendar day per student — a second scan on the same day does not create a second row. Remove the existing-record check in `AttendanceController@store` if multiple check-ins per day should be allowed instead.
- `tenantId`/`locationId` are fixed per deployment via `.env` and are never accepted from the client, by design (single-tenant deployment as specified).
