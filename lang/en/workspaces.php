<?php

declare(strict_types=1);

return [
    'title' => 'Workspaces',
    'select_title' => 'Your workspaces',
    'select_description' => 'Select a workspace to continue',
    'current' => 'Current',
    'connections' => ':count connections',
    'posts' => ':count posts',
    'subscription_required' => 'Subscribe to create more workspaces.',
    'limit_reached' => 'Your plan includes one workspace. Upgrade to add more.',

    'upgrade_dialog' => [
        'title' => 'Upgrade to add workspaces',
        'description' => 'Your plan includes one workspace. Upgrade to Workspaces for unlimited workspaces — one for each brand or client.',
    ],

    'create' => [
        'page_title' => 'Create your workspace',
        'title' => 'Set up your workspace',
        'description' => 'Give your workspace a name to get started.',
        'autofill_errors' => [
            'unreachable' => 'We could not reach that website (:reason).',
            'http_status' => 'The website returned an unexpected status (:status).',
            'invalid_scheme' => 'Only http and https URLs are supported.',
            'missing_host' => 'The URL is missing a host.',
            'unresolvable_host' => 'We could not resolve the host (:host).',
            'private_network' => 'URLs pointing to private networks are not allowed.',
        ],
        'name' => 'Workspace name',
        'name_placeholder' => 'e.g. Acme Inc',
        'submit' => 'Create workspace',
        'success' => 'Workspace created. Connect a social account to start posting.',
    ],

    'cannot_delete_last' => 'You cannot delete your only workspace. Cancel your subscription in billing settings to close your account.',

    'flash' => [
        'deleted' => 'Workspace deleted successfully.',
    ],
];
