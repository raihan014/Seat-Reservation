# Seat Reservation API

A Laravel-based backend API for managing events and seat reservations.

This project was developed as a backend internship task, with a focus on:

- REST API development
- Authentication using Laravel Sanctum
- Event and reservation management
- Seat capacity validation
- Duplicate reservation prevention
- Reservation cancellation
- Database transactions
- Concurrent request handling
- Database indexing and optimization
- Performance and concurrency testing

---

## Features

### Authentication

- User authentication using Laravel Sanctum
- Token-based API authentication
- Protected reservation endpoints

### Event Management

- Retrieve available events
- Display event capacity
- Display current reserved seat count

### Seat Reservation

- Authenticated users can reserve a seat
- Prevents reservations when an event is full
- Prevents duplicate active reservations
- Supports re-reservation after cancellation
- Uses database transactions for consistency
- Returns appropriate HTTP status codes

### Reservation Cancellation

- Users can cancel their own reservations
- Users cannot cancel another user's reservation
- Cancelled reservations release the reserved seat
- Prevents cancelling an already cancelled reservation

### Concurrency Protection

The reservation process uses database transactions and row-level locking to prevent race conditions when multiple users attempt to reserve the final available seat simultaneously.

The event row is locked using:

```php
lockForUpdate()
```
This ensures that concurrent reservation requests are processed safely.

---

## Technology Stack

| Technology      | Purpose                       |
| --------------- | ----------------------------- |
| PHP             | Backend programming language  |
| Laravel         | Backend framework             |
| Laravel Sanctum | API authentication            |
| MySQL           | Relational database           |
| XAMPP           | Local development environment |
| Eloquent ORM    | Database interaction          |
| REST API        | Client-server communication   |
| cURL            | API testing                   |
| Git             | Version control               |


---
## API Endpoints

All reservation-related endpoints require a valid Laravel Sanctum bearer token.

| Method | Endpoint                                 | Description          | Authentication |
| ------ | ---------------------------------------- | -------------------- | -------------- |
| GET    | `/api/events`                            | Retrieve all events  | Required       |
| POST   | `/api/events/{event}/reserve`            | Reserve a seat       | Required       |
| POST   | `/api/reservations/{reservation}/cancel` | Cancel a reservation | Required       |

### Example Request

Reserve a seat:
```text
POST /api/events/{event}/reserve
Authorization: Bearer {token}
Accept: application/json

```

Cancel a reservation:
```text
POST /api/reservations/{reservation}/cancel
Authorization: Bearer {token}
Accept: application/json
```

### Response Status Codes

| Status Code | Meaning                               |
| ----------- | ------------------------------------- |
| 200         | Request completed successfully        |
| 201         | Reservation created successfully      |
| 403         | User is not authorized                |
| 409         | Reservation conflict or event is full |

---
## Database Schema

The application uses three main tables:

- `users` — Stores authenticated user information.
- `events` — Stores event information, including capacity and reserved seat count.
- `reservations` — Stores user reservations and their current status.

### Reservation Status

Reservations can have the following statuses:

- `reserved`
- `cancelled`

A unique database constraint on `event_id` and `user_id` prevents the same user from having duplicate reservations for the same event.

---

## Testing & Performance

### Concurrency Test

The application was tested with multiple users attempting to reserve seats concurrently.

Test result:

- 50 concurrent reservation requests
- Event capacity: 1
- Successful reservations: 1
- Conflict responses (409): 49
- Other errors: 0
- Final reserved count: 1
- Final active reservations: 1

The test confirms that concurrent requests cannot reserve more seats than the event capacity.

### Performance Benchmark

A benchmark was performed using 50 concurrent requests against an event with a capacity of 50.

The benchmark verifies:

- All requests completed successfully
- Database state remained consistent
- Reserved count matched active reservations
- No overbooking occurred
---

### Benchmark Result

The benchmark produced the following result:

| Metric           |      Result |
| ---------------- | ----------: |
| Requests         |          50 |
| Successful (201) |          50 |
| Conflicts (409)  |           0 |
| Other errors     |           0 |
| Average response | 1,744.72 ms |
| Fastest response | 1,058.37 ms |
| Slowest response | 2,457.76 ms |
| Total wall time  | 2,463.52 ms |

The final database state was consistent:

- Event capacity: 50
- Reserved count: 50
- Active reservations: 50

---

## Environment and Security

The application uses environment variables for database and application configuration.

The `.env` file contains local configuration and must not be committed to the repository.

The repository should contain `.env.example` instead, so other developers can create their own local environment configuration.

Authentication is handled using Laravel Sanctum bearer tokens, and protected API endpoints require valid authentication.

---

## Development Notes

The project was developed and tested locally using:

- Laravel
- PHP
- MySQL
- XAMPP
- Laravel Sanctum
- Composer

Database migrations are used to create and modify the application database structure.

The reservation logic uses database transactions and row-level locking to maintain consistency during concurrent requests.

---

## Testing Summary

The application was tested for both correctness and concurrent reservation handling.

### Correctness Test

The database state was verified after reservation operations to ensure that:

- Reserved seat counts remain consistent.
- Active reservations match the reserved count.
- Cancelled reservations do not remain active.
- Duplicate reservations are prevented.

### Concurrency Test

A test with 50 users attempting to reserve a single available seat resulted in:

- 1 successful reservation
- 49 conflict responses
- 0 other errors
- 1 final reserved seat
- 1 active reservation

### Performance Test

A benchmark with 50 concurrent requests against an event with capacity 50 resulted in:

- 50 successful reservations
- 0 conflicts
- 0 other errors
- 50 reserved seats
- 50 active reservations

These tests demonstrate that the reservation system maintains the expected database state under concurrent requests.

---

## How to Run the Project

After configuring the environment and database, start the Laravel development server:

```bash
php artisan serve
```
The API will be available at:

`http://127.0.0.1:8000`

Then use an API client such as Postman or cURL to test the available endpoints.

---

## Project Status

The backend API implementation is complete and has been tested for:

- Authentication
- Event retrieval
- Seat reservation
- Reservation cancellation
- Capacity validation
- Duplicate reservation prevention
- Concurrent reservation handling
- Database consistency
- Performance benchmarking

---

## License

This project was developed as part of a backend internship task.

