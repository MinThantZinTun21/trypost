<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

test('credits used between sums credits within the window', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    AiUsageLog::factory()->text(credits: 5)->count(3)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'created_at' => Carbon::parse('2026-06-10'),
    ]);

    AiUsageLog::factory()->text(credits: 10)->count(2)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'created_at' => Carbon::parse('2026-06-12'),
    ]);

    $used = AiUsageLog::creditsUsedBetween(
        $account->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-07-01'),
    );

    expect($used)->toBe(35);
});

test('credits used between excludes logs outside the window', function () {
    $account = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);

    AiUsageLog::factory()->text(credits: 10)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'created_at' => Carbon::parse('2026-06-10'),
    ]);

    AiUsageLog::factory()->text(credits: 50)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'created_at' => Carbon::parse('2026-05-10'),
    ]);

    $used = AiUsageLog::creditsUsedBetween(
        $account->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-07-01'),
    );

    expect($used)->toBe(10);
});

test('credits used between excludes logs from other accounts', function () {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    $workspace = Workspace::factory()->create(['account_id' => $account->id]);
    $otherWorkspace = Workspace::factory()->create(['account_id' => $otherAccount->id]);

    AiUsageLog::factory()->text(credits: 10)->create([
        'account_id' => $account->id,
        'workspace_id' => $workspace->id,
        'created_at' => Carbon::parse('2026-06-10'),
    ]);

    AiUsageLog::factory()->text(credits: 100)->create([
        'account_id' => $otherAccount->id,
        'workspace_id' => $otherWorkspace->id,
        'created_at' => Carbon::parse('2026-06-10'),
    ]);

    $used = AiUsageLog::creditsUsedBetween(
        $account->id,
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-07-01'),
    );

    expect($used)->toBe(10);
});
