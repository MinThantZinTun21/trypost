<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Mail\WorkspaceConnectionsDisconnected;
use App\Mail\WorkspaceInvite;
use App\Models\Account;
use App\Models\Invite;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Mail;

test('the workspace invite renders the account and role', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $invite = Invite::factory()->create([
        'account_id' => $account->id,
        'email' => 'invitee@example.com',
        'role' => Role::Member,
    ]);

    $mailable = new WorkspaceInvite($invite);

    $mailable->assertHasSubject(__('mail.workspace_invite.subject', ['account' => 'Acme Co']));
    $mailable->assertSeeInHtml(__('mail.workspace_invite.heading'));
    $mailable->assertSeeInHtml(__('mail.workspace_invite.expiry'));
    $mailable->assertSeeInHtml('Acme Co');
    $mailable->assertSeeInHtml(Role::Member->label());
});

test('the disconnected-connections digest renders every account and reason', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'account_id' => $user->account_id,
        'user_id' => $user->id,
        'name' => 'Acme Workspace',
    ]);

    $accounts = collect([Platform::LinkedIn, Platform::X])->map(
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

function sentNotificationHtml(User $user, BaseNotification $notification): string
{
    Mail::mailer()->getSymfonyTransport()->messages()->take(0);

    $user->notify($notification);

    $message = Mail::mailer()->getSymfonyTransport()->messages()->last();

    return (string) $message->getOriginalMessage()->getHtmlBody();
}

test('the verification email renders the translated copy', function () {
    $user = User::factory()->create();

    expect(sentNotificationHtml($user, new VerifyEmail))
        ->toContain(__('mail.email_verification.body'))
        ->toContain(__('mail.email_verification.button'))
        ->toContain(__('mail.layout.team'));
});

test('the password reset email renders the translated copy', function () {
    $user = User::factory()->create();

    expect(sentNotificationHtml($user, new ResetPassword('token-123')))
        ->toContain(__('mail.password_reset.body'))
        ->toContain(__('mail.password_reset.expiry'));
});
