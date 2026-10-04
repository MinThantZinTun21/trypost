<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasAccount;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasWorkspace;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAccount, HasFactory, HasMedia, HasUuids, HasWorkspace;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_id',
        'current_workspace_id',
        'email_verified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected $appends = [
        'has_photo',
        'photo_url',
    ];

    public function getHasPhotoAttribute(): bool
    {
        return $this->getFirstMedia('avatar') !== null;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar');
    }

    /**
     * First whitespace-delimited token of the display name (empty when unset).
     */
    public function firstName(): string
    {
        return (string) Str::of($this->name ?? '')->trim()->before(' ');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
