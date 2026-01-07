<?php

/**
 * ArtisanPack UI Forms Configuration
 *
 * This configuration file defines all settings for the Forms package
 * including storage, submissions, spam protection, and notification defaults.
 *
 * @since   1.0.0
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Admin Interface Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the admin interface for managing forms. You can customize the
    | route prefix, middleware, and pagination settings here.
    |
    */

    'admin' => [
        'prefix' => env('FORMS_ADMIN_PREFIX', 'admin/forms'),
        'middleware' => ['web', 'auth'],
        'per_page' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Uploads Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the storage disk used for form file uploads. The default disk
    | is 'form-uploads' which stores files privately in storage/app/form-uploads.
    | You can change this to any configured disk in your filesystems.php.
    |
    */

    'uploads' => [
        'disk' => env('FORMS_UPLOADS_DISK', 'form-uploads'),
        'directory' => env('FORMS_UPLOADS_DIRECTORY', 'uploads'),
        'max_size' => env('FORMS_UPLOADS_MAX_SIZE', 10240), // KB (10MB default)
        'allowed_mimes' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'text/plain',
            'text/csv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Submissions Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how form submissions are stored and managed. Set retention_days
    | to null to keep submissions forever, or specify a number of days after
    | which old submissions will be pruned.
    |
    */

    'submissions' => [
        'store_submissions' => true,
        'retention_days' => env('FORMS_RETENTION_DAYS', null), // null = keep forever
        'submission_number_format' => 'FORM-{year}-{sequence}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam Protection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure spam protection features for forms. The honeypot field creates
    | a hidden field that bots typically fill out, and rate limiting prevents
    | rapid-fire submissions from the same IP address.
    |
    */

    'spam_protection' => [
        'honeypot' => [
            'enabled' => true,
            'field_name' => 'website_url',
        ],
        'rate_limit' => [
            'enabled' => true,
            'attempts' => 5,
            'decay' => 60, // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Defaults
    |--------------------------------------------------------------------------
    |
    | Default values for form notifications. These can be overridden per-form
    | in the notification configuration. If null, defaults to app.name and
    | mail.from.address respectively at runtime.
    |
    */

    'notifications' => [
        'from_name' => env('FORMS_FROM_NAME'),
        'from_email' => env('FORMS_FROM_EMAIL'),
        'queue' => env('FORMS_NOTIFICATION_QUEUE', 'default'),
        'show_ip_in_emails' => env('FORMS_SHOW_IP_IN_EMAILS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Display Defaults
    |--------------------------------------------------------------------------
    |
    | Default display settings for new forms. These can be overridden per-form.
    |
    */

    'display' => [
        'label_position' => 'above', // 'above', 'beside', 'hidden'
        'show_required_indicator' => true,
        'required_indicator' => '*',
        'error_display' => 'below', // 'below', 'tooltip', 'summary'
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure global webhook settings for form submissions. Webhooks can also
    | be configured per-form in the form's settings. The global webhook is sent
    | for all form submissions if enabled.
    |
    */

    'webhooks' => [
        'enabled' => env('FORMS_WEBHOOKS_ENABLED', false),
        'url' => env('FORMS_WEBHOOK_URL'),
        'secret' => env('FORMS_WEBHOOK_SECRET'),
        'queue' => env('FORMS_WEBHOOK_QUEUE', 'default'),
        'timeout' => env('FORMS_WEBHOOK_TIMEOUT', 30),
        'retry_times' => 3,
        'retry_backoff' => [10, 60, 300], // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    |
    | Configure settings for third-party integrations. Integration packages
    | can register their own settings via the 'forms.settings_tabs' filter hook.
    |
    */

    'integrations' => [
        // Enable or disable the integration settings panel
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy Settings
    |--------------------------------------------------------------------------
    |
    | Configure privacy settings for handling personally identifiable information
    | (PII) in exports and webhooks. By default, PII fields like IP address and
    | user agent are excluded to comply with privacy regulations.
    |
    */

    'privacy' => [
        // Include IP address in exports and webhooks
        'include_ip_address' => env('FORMS_INCLUDE_IP_ADDRESS', false),

        // Include user agent in webhooks
        'include_user_agent' => env('FORMS_INCLUDE_USER_AGENT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disk Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration will be merged into the filesystems.disks config
    | when the package service provider boots. If you want to use a different
    | disk configuration, you can override this in your filesystems.php.
    |
    */

    'disk_config' => [
        'form-uploads' => [
            'driver' => 'local',
            'root' => storage_path('app/form-uploads'),
            'visibility' => 'private',
        ],
    ],

];
