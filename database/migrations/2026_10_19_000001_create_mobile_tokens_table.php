<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile tokens (ADR 0015): the secret a phone gets when the Owner signs in to
 * the Mobile app with their web password. Only its SHA-256 hash is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_tokens');
    }
};
