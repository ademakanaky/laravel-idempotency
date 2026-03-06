# Laravel Enterprise Idempotency

![Laravel](https://img.shields.io/badge/Laravel-10%2B-red)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen)

**Enterprise-grade Idempotency Middleware for Laravel APIs**

This package prevents **duplicate request processing** and safely
**replays the original response** when a client retries a request using
the same `Idempotency-Key`.

It is designed for **financial systems, payment APIs, fintech platforms,
loan services, and mission‑critical backend systems** where duplicate
processing must be prevented.

------------------------------------------------------------------------

# Table of Contents

-   Features
-   Installation
-   Basic Usage
-   How Idempotency Works
-   Drivers
-   Configuration
-   Request Hash Protection
-   Automatic User Scoping
-   Response Replay
-   Example Payment Flow
-   Testing
-   Package Structure
-   Security Considerations
-   Example Idempotency Key Generation
-   Contributing
-   License

------------------------------------------------------------------------

# Features

-   Multiple idempotency drivers
-   Cache driver
-   Database driver
-   Hybrid driver (Database + Cache Lock)
-   Automatic response replay
-   Request hash validation
-   Per-user idempotency scope
-   Configurable response status codes
-   Distributed locking support
-   Replay count tracking
-   Pluggable driver architecture
-   Fully tested (Unit + Feature tests)

------------------------------------------------------------------------

# Installation

Install the package via Composer:

``` bash
composer require ademakanaky/laravel-idempotency
```

Publish the configuration:

``` bash
php artisan vendor:publish --tag=idempotency-config
```

Publish the migration:

``` bash
php artisan vendor:publish --tag=idempotency-migrations
```

Run migrations:

``` bash
php artisan migrate
```

------------------------------------------------------------------------

# Basic Usage

Apply the middleware to routes that require idempotency protection.

``` php
Route::post('/payments', PaymentController::class)
    ->middleware('idempotency');
```

Clients must include an **Idempotency-Key** header.

Example request:

    POST /payments
    Idempotency-Key: PAY-123456789

------------------------------------------------------------------------

# How Idempotency Works

## First Request

Client sends request:

    Idempotency-Key: abc123

1.  The request is processed normally.
2.  The response is stored.
3.  The idempotency key is recorded.

------------------------------------------------------------------------

## Retry Request

Client retries using the same key:

    Idempotency-Key: abc123

Instead of executing the request again:

-   The stored response is returned.
-   The request handler is not executed again.

Response header:

    Idempotency-Replayed: true

------------------------------------------------------------------------

# Drivers

Driver configuration is located at:

    config/idempotency.php

## Cache Driver

Uses Laravel cache to store responses.

Best for:

-   Short‑lived APIs
-   Lightweight operations

```{=html}
<!-- -->
```
    driver = cache

------------------------------------------------------------------------

## Database Driver

Stores request and response data in the database.

Best for:

-   Financial systems
-   Audit trails
-   Compliance environments

```{=html}
<!-- -->
```
    driver = database

------------------------------------------------------------------------

## Hybrid Driver (Recommended)

Uses:

-   Cache locking
-   Database persistence

Benefits:

-   Prevents concurrent duplicate processing
-   Ensures durable response replay

```{=html}
<!-- -->
```
    driver = hybrid

------------------------------------------------------------------------

# Configuration

Example configuration file:

``` php
return [

    'driver' => env('IDEMPOTENCY_DRIVER', 'hybrid'),

    'ttl_minutes' => 60,

    'status_codes' => [
        200,
        201,
        202,
    ],

    'lock' => [
        'enabled' => true,
        'seconds' => 10,
    ],

    'idempotency_model' =>
        \Ademakanaky\EnterpriseIdempotency\Models\IdempotencyRecord::class,

];
```

------------------------------------------------------------------------

# Request Hash Protection

Requests are hashed using:

    HTTP Method + Route + Request Body

If the same `Idempotency-Key` is reused with **different request data**,
the request will fail.

Example response:

    409 Conflict
    Idempotency key conflict

This ensures request integrity.

------------------------------------------------------------------------

# Automatic User Scoping

Idempotency keys are automatically scoped to avoid collisions.

Priority:

1.  Authenticated User ID
2.  Client IP Address

This prevents two different users from accidentally sharing the same
idempotency key.

------------------------------------------------------------------------

# Response Replay

When a stored response is returned, the API includes the header:

    Idempotency-Replayed: true

This allows API clients to detect that a response came from a retry.

------------------------------------------------------------------------

# Example Payment Flow

### Initial Request

    POST /payments
    Idempotency-Key: PAY-001

A network failure occurs before the client receives the response.

### Client Retry

    POST /payments
    Idempotency-Key: PAY-001

Instead of creating a second payment, the API returns the **original
stored response**.

------------------------------------------------------------------------

# Architecture Overview

    Client
      |
      |  POST /payments
      |  Idempotency-Key
      v
    Idempotency Middleware
      |
      |-- Check existing key
      |-- Validate request hash
      |-- Acquire lock (Hybrid)
      |
      v
    Application Controller
      |
      v
    Response Stored
      |
      v
    Future retries replay stored response

------------------------------------------------------------------------

# Testing

Run the full test suite:

``` bash
vendor/bin/phpunit
```

Test coverage includes:

-   Unit Tests
-   Feature Tests
-   Driver Tests
-   Middleware Concurrency Tests

------------------------------------------------------------------------

# Package Structure

    laravel-idempotency
    │
    ├── src
    │   ├── Contracts
    │   │   └── IdempotencyDriver.php
    │   │
    │   ├── Drivers
    │   │   ├── CacheDriver.php
    │   │   ├── DatabaseDriver.php
    │   │   └── HybridDriver.php
    │   │
    │   ├── Http
    │   │   └── Middleware
    │   │       └── IdempotencyMiddleware.php
    │   │
    │   ├── Models
    │   │   └── IdempotencyRecord.php
    │   │
    │   ├── Providers
    │   │   └── IdempotencyServiceProvider.php
    │   │
    │   └── Services
    │       └── IdempotencyManager.php
    │
    ├── config
    │   └── idempotency.php
    │
    ├── database
    │   └── migrations
    │
    ├── tests
    │   ├── Feature
    │   └── Unit
    │
    ├── composer.json
    └── phpunit.xml

------------------------------------------------------------------------

# Security Considerations

This package ensures:

-   Duplicate requests are **not processed twice**
-   Safe retry behavior
-   Request integrity validation
-   Replay protection

Recommended for:

-   Payment APIs
-   Loan systems
-   Order processing
-   Financial microservices
-   Distributed systems

------------------------------------------------------------------------

# Example Idempotency Key Generation

Clients should generate unique idempotency keys.

Example in Laravel:

``` php
use Illuminate\Support\Str;

$key = 'PAY-' . Str::uuid();
```

Example request header:

    Idempotency-Key: PAY-550e8400-e29b-41d4-a716-446655440000

------------------------------------------------------------------------

# Contributing

Pull requests are welcome.

Please ensure all tests pass before submitting:

``` bash
vendor/bin/phpunit
```

------------------------------------------------------------------------

# License

MIT License
