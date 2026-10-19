<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MobileTokenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The secret one phone holds after the Owner signs in to the Mobile app with
 * their web password (ADR 0015). The plain token is handed out once and never
 * stored; only its SHA-256 hash is. Signing out on the phone deletes it, and
 * a password change deletes every one of them.
 */
class MobileToken extends Model
{
    /** @use HasFactory<MobileTokenFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'token_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Stores a new token for the user and returns its plain value, the only
     * time it exists.
     */
    public static function issueFor(User $user): string
    {
        $plainToken = bin2hex(random_bytes(40));

        $user->mobileTokens()->create(['token_hash' => self::hash($plainToken)]);

        return $plainToken;
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        return self::query()->where('token_hash', self::hash($plainToken))->first();
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
