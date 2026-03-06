<?php

return [

    'enabled' => true,

    'header' => 'Idempotency-Key',

    'driver' => 'hybrid', // database | cache | hybrid

    'apply_to_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

    'ttl_minutes' => 60,

    'lock' => [
        'enabled' => true,
        'seconds' => 10,
        'store' => null, // null = default
    ],

    'status_codes' => [200, 201, 202, 204, 422],

    'user_resolver' => null,

    'idempotency_model' => Ademakanaky\EnterpriseIdempotency\Models\IdempotencyRecord::class,

];
