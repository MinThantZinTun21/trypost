<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signup, social login, onboarding and forgot-password are gone: drop the
     * columns and table only they used.
     */
    public function up(): void
    {
        $this->dropColumns('users', [
            'google_id',
            'github_id',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'li_fat_id',
            'ttclid',
            'rdt_cid',
            'epik',
            'registration_ip',
            'persona',
            'goals',
            'referral_source',
        ]);

        $this->dropColumns('accounts', [
            'onboarding_completed_at',
            'onboarding_dismissed_at',
            'onboarding_skipped_steps',
        ]);

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        // Irreversible: the features these columns served are removed.
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumns(string $table, array $columns): void
    {
        $existing = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($existing): void {
            $blueprint->dropColumn($existing);
        });
    }
};
