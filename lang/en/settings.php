<?php

return [
    'title' => 'Settings',
    'description' => 'Manage your profile and account settings',

    'hub' => [
        'title' => 'Settings',
        'description' => 'Choose what you want to manage.',
        'profile' => [
            'title' => 'Profile',
            'description' => 'Update your personal info, password, and notification preferences.',
        ],
    ],

    'nav' => [
        'profile' => 'Profile',
        'authentication' => 'Authentication',
        'notifications' => 'Notifications',
    ],

    'notifications' => [
        'title' => 'Notification preferences',
        'heading' => 'Email notifications',
        'description' => 'Choose which email notifications you want to receive',
        'post_published' => 'Post published',
        'post_published_description' => 'Receive an email when your post is published successfully',
        'post_failed' => 'Post failed',
        'post_failed_description' => 'Receive an email when your post fails to publish',
        'account_disconnected' => 'Account disconnected',
        'account_disconnected_description' => 'Receive an email when a social account is disconnected',
        'save' => 'Save preferences',
    ],

    'profile' => [
        'title' => 'Profile settings',
        'photo_heading' => 'Profile photo',
        'photo_description' => 'Upload a profile photo',
        'heading' => 'Profile information',
        'description' => 'Update your name and email address',
        'avatar' => 'Avatar',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'email_placeholder' => 'Email address',
        'save' => 'Save',
    ],

    'authentication' => [
        'title' => 'Authentication',
        'page_title' => 'Authentication settings',
        'sessions' => [
            'title' => 'Active sessions',
            'description' => 'If you notice anything suspicious, sign out of other devices.',
            'unknown_browser' => 'Unknown browser',
            'unknown_ip' => 'Unknown IP',
            'on' => 'on',
            'active_now' => 'Active now',
            'log_out_others' => 'Log out other devices',
            'modal_title' => 'Log out other devices',
            'modal_description_password' => 'Enter your current password to confirm you want to log out other browser sessions.',
            'password_placeholder' => 'Current password',
            'cancel' => 'Cancel',
            'submit' => 'Log out other devices',
            'flash_logged_out' => 'You have been logged out from other devices.',
        ],
        'password' => [
            'update_title' => 'Update password',
            'update_description' => 'Ensure your account is using a long, random password to stay secure.',
            'current_password' => 'Current password',
            'new_password' => 'New password',
            'confirm_password' => 'Confirm password',
            'save' => 'Save password',
        ],
    ],

    'delete_account' => [
        'heading' => 'Delete account',
        'description' => 'Delete your account and all of its resources',
        'warning' => 'Warning',
        'warning_message' => 'Please proceed with caution, this cannot be undone.',
        'button' => 'Delete account',
        'modal_title' => 'Are you sure you want to delete your account?',
        'modal_description_password' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm.',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'cancel' => 'Cancel',
        'confirm' => 'Delete account',
    ],

    'flash' => [
        'profile_updated' => 'Profile updated successfully!',
        'password_updated' => 'Password updated successfully!',
        'photo_updated' => 'Photo updated successfully!',
        'photo_deleted' => 'Photo removed successfully!',
        'notifications_updated' => 'Notification preferences updated!',
    ],
];
