<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasMedia;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasMedia, HasUuids;

    protected $fillable = [
        'account_id',
        'user_id',
        'name',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function contentIdeas(): HasMany
    {
        return $this->hasMany(ContentIdea::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->account?->owner_id === $user->id || $this->members()->where('user_id', $user->id)->exists();
    }
}
