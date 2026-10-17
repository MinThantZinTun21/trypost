<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasMedia;
use Database\Factories\VideoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A name and a video file the Owner uploads from the Mobile app for the
 * Assistant (ADR 0013). It is not part of any Post. Its file is a Media row
 * attached to the Video, never a workspace asset, so the composer and
 * `create_post` cannot use it; deleting the Video deletes the file.
 */
class Video extends Model
{
    /** @use HasFactory<VideoFactory> */
    use HasFactory, HasMedia, HasUuids;

    /** The Media collection that holds the file. */
    public const string FILE_COLLECTION = 'file';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Video $video): void {
            $video->clearMediaCollection(self::FILE_COLLECTION);
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Who uploaded the Video.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')->where('collection', self::FILE_COLLECTION);
    }
}
