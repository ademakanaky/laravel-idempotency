<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('idempotency_key', 128);
            $table->string('route');
            $table->string('method');
            $table->string('request_hash', 64);
            $table->string('user_identifier')->nullable();
            $table->string('ip_address')->nullable();
            $table->integer('status_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->unsignedInteger('replay_count')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'idempotency_key',
                'route',
                'method',
                'user_identifier',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
