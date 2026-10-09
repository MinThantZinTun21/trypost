<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentIdea\Status;
use App\Enums\Post\CreatedVia;
use Database\Factories\ContentIdeaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Something the Owner might post later: a title and Markdown details that the
 * Owner or the Assistant writes, moving from New to In progress to Done.
 */
class ContentIdea extends Model
{
    /** @use HasFactory<ContentIdeaFactory> */
    use HasFactory, HasUuids;

    private const int EXCERPT_LENGTH = 160;

    protected $fillable = [
        'workspace_id',
        'title',
        'details',
        'status',
        'created_via',
    ];

    protected $attributes = [
        'status' => Status::New->value,
        'created_via' => CreatedVia::Web->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'created_via' => CreatedVia::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The details rendered from Markdown. Raw HTML is escaped and unsafe links
     * (javascript:, data: and the like) are dropped, because the Assistant can
     * write the details.
     */
    public function detailsHtml(): string
    {
        return Str::markdown((string) $this->details, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * A short plain-text preview of the details, taken from the rendered
     * Markdown so headings and list markers do not show.
     */
    public function excerpt(): string
    {
        $text = html_entity_decode(strip_tags($this->detailsHtml()), ENT_QUOTES | ENT_HTML5);

        return Str::limit(Str::squish($text), self::EXCERPT_LENGTH);
    }
}
