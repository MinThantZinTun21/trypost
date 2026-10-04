<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Mail\WorkspaceConnectionsDisconnected;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

test('the disconnected-connections digest renders every account and reason', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
        'name' => 'Acme Workspace',
    ]);

    $accounts = collect([Platform::Facebook, Platform::TikTok])->map(
        fn (Platform $platform) => SocialAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'platform' => $platform,
        ]),
    );

    $mailable = new WorkspaceConnectionsDisconnected($workspace, $accounts);

    $mailable->assertHasSubject(trans_choice(
        'mail.workspace_connections_disconnected.subject',
        2,
        ['count' => 2, 'workspace' => 'Acme Workspace'],
    ));

    $mailable->assertSeeInHtml(__('mail.workspace_connections_disconnected.heading'));
    $mailable->assertSeeInHtml(__('mail.workspace_connections_disconnected.reason_revoked'));
    $mailable->assertSeeInHtml('Acme Workspace');

    foreach ($accounts as $account) {
        $mailable->assertSeeInHtml($account->platform->label());
    }
});
