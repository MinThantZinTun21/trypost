<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $accountColumns = [
        'billing_email',
        'plan_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    public function up(): void
    {
        $hasPlanForeignKey = collect(Schema::getForeignKeys('accounts'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['plan_id']);

        if ($hasPlanForeignKey) {
            Schema::table('accounts', function (Blueprint $table): void {
                $table->dropForeign(['plan_id']);
            });
        }

        $columns = array_values(array_filter(
            $this->accountColumns,
            fn (string $column): bool => Schema::hasColumn('accounts', $column),
        ));

        if ($columns !== []) {
            Schema::table('accounts', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }

        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }

    public function down(): void
    {
        // Irreversible: billing, plans and subscriptions are gone for good.
    }
};
