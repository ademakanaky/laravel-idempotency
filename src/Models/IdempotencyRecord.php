<?php

namespace Ademakanaky\EnterpriseIdempotency\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    protected $fillable = [
        'idempotency_key',
        'route',
        'method',
        'request_hash',
        'user_identifier',
        'ip_address',
        'status_code',
        'response_body',
        'response_headers',
        'replay_count',
        'locked_at',
        'completed_at',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'replay_count' => 'integer',
        'locked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Check if the record is completed
    public function isCompleted(): bool
    {
        return ! is_null($this->completed_at);
    }

    // Increment replay count safely
    public function incrementReplay(): void
    {
        $this->increment('replay_count');
    }
}


