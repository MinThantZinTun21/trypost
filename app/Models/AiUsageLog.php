<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Ai\UsageType;
use Carbon\CarbonInterface;
use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory, HasUuids;

    protected $table = 'workspace_ai_usages';

    protected $fillable = [
        'account_id',
        'workspace_id',
        'user_id',
        'post_id',
        'type',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'credits',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => UsageType::class,
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'credits' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function creditsUsedBetween(string $accountId, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) static::where('account_id', $accountId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->sum('credits');
    }
}
