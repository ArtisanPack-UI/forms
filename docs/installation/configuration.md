---
title: Configuration
---

# Configuration

ArtisanPack UI Forms is highly configurable through the `config/artisanpack/forms.php` configuration file.

## Publishing the Configuration

```bash
php artisan vendor:publish --tag=forms-config
```

## Configuration Sections

### Admin Interface

Configure the admin panel routes and settings:

```php
'admin' => [
    // URL prefix for admin routes (e.g., /admin/forms)
    'prefix' => env('FORMS_ADMIN_PREFIX', 'admin/forms'),

    // Middleware applied to admin routes
    'middleware' => ['web', 'auth'],

    // Items per page in admin lists
    'per_page' => 15,
],
```

### File Uploads

Configure file upload handling:

```php
'uploads' => [
    // Storage disk for uploads
    'disk' => env('FORMS_UPLOADS_DISK', 'form-uploads'),

    // Directory within the disk
    'directory' => env('FORMS_UPLOADS_DIRECTORY', 'uploads'),

    // Maximum file size in KB (10MB default)
    'max_size' => env('FORMS_UPLOADS_MAX_SIZE', 10240),

    // Allowed MIME types
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

    // File fields whose Storage setting is "Media Library" (since 1.6.0)
    'media_library' => [
        // A media folder to file the uploads in
        'folder_id' => env('FORMS_MEDIA_FOLDER_ID'),

        // The user media items are attributed to (for guest uploads)
        'uploaded_by' => env('FORMS_MEDIA_UPLOADED_BY'),
    ],

    // Images uploaded in the builders for image choices (since 1.6.0)
    'choice_images' => [
        // Public disk used when artisanpack-ui/media-library is not installed
        'disk' => env('FORMS_CHOICE_IMAGES_DISK', 'public'),

        // Directory within the disk
        'directory' => env('FORMS_CHOICE_IMAGES_DIRECTORY', 'forms/choice-images'),

        // Maximum image size in KB (JPEG, PNG, GIF and WebP only)
        'max_size' => env('FORMS_CHOICE_IMAGES_MAX_SIZE', 2048),
    ],
],
```

`media_library` applies to file fields whose **Storage** setting is **Media Library**. Those files live on the media library's disk and follow its visibility, which is public by default, and fields with **Mask in admin** or **Do not store** on never use it. See [File Uploads](Usage-File-Uploads#media-library-storage).

Choice images are shown on the public form, so they are stored with `artisanpack-ui/media-library` when it is installed, or on the public `choice_images.disk` otherwise. SVG is never accepted. See [Image Choices](Usage-Form-Builder#image-choices).

### Submissions

Configure submission storage and retention:

```php
'submissions' => [
    // Whether to store submissions in the database
    'store_submissions' => true,

    // Days to retain submissions (null = forever)
    'retention_days' => env('FORMS_RETENTION_DAYS', null),

    // Submission number format
    // Available placeholders: {year}, {month}, {day}, {sequence}, {form_id}
    'submission_number_format' => 'FORM-{year}-{sequence}',
],
```

### Spam Protection

Configure built-in spam protection:

```php
'spam_protection' => [
    // Honeypot field (hidden field that bots fill out)
    'honeypot' => [
        'enabled' => true,
        'field_name' => 'website_url',
    ],

    // Rate limiting
    'rate_limit' => [
        'enabled' => true,
        'attempts' => 5,        // Max submissions
        'decay' => 60,          // Time window in seconds
    ],

    // CAPTCHA field providers (see Spam Protection)
    'captcha' => [
        'driver'  => env('FORMS_CAPTCHA_DRIVER', 'turnstile'),
        'timeout' => 5,         // Seconds to wait for the provider
        'drivers' => [
            'turnstile'    => ['site_key' => env('FORMS_TURNSTILE_SITE_KEY'), 'secret_key' => env('FORMS_TURNSTILE_SECRET_KEY')],
            'hcaptcha'     => ['site_key' => env('FORMS_HCAPTCHA_SITE_KEY'), 'secret_key' => env('FORMS_HCAPTCHA_SECRET_KEY')],
            'recaptcha_v2' => ['site_key' => env('FORMS_RECAPTCHA_V2_SITE_KEY'), 'secret_key' => env('FORMS_RECAPTCHA_V2_SECRET_KEY')],
            'recaptcha_v3' => [
                'site_key'        => env('FORMS_RECAPTCHA_V3_SITE_KEY'),
                'secret_key'      => env('FORMS_RECAPTCHA_V3_SECRET_KEY'),
                'score_threshold' => env('FORMS_RECAPTCHA_V3_THRESHOLD', 0.5),
                'action'          => 'forms_submit',
            ],
        ],
    ],
],
```

`captcha` configures the CAPTCHA field (since 1.6.0). `driver` is the provider a field uses unless it picks its own: `turnstile`, `hcaptcha`, `recaptcha_v2` or `recaptcha_v3`, or a driver registered through the `ap.forms.captchaDrivers` filter. A provider without both keys renders nothing and checks nothing. See [Spam Protection](Advanced-Spam-Protection#captcha).

### Notifications

Configure email notification defaults:

```php
'notifications' => [
    // Default "from" name (null = app.name)
    'from_name' => env('FORMS_FROM_NAME'),

    // Default "from" email (null = mail.from.address)
    'from_email' => env('FORMS_FROM_EMAIL'),

    // Queue name for notification jobs
    'queue' => env('FORMS_NOTIFICATION_QUEUE', 'default'),

    // Include IP address in admin notification emails
    'show_ip_in_emails' => env('FORMS_SHOW_IP_IN_EMAILS', true),
],
```

### Display Defaults

Configure default form display settings:

```php
'display' => [
    // Label position: 'above', 'beside', 'hidden'
    'label_position' => 'above',

    // Show asterisk for required fields
    'show_required_indicator' => true,

    // Required field indicator character
    'required_indicator' => '*',

    // Error display: 'below', 'tooltip', 'summary'
    'error_display' => 'below',
],
```

### Webhooks

Configure webhook delivery settings:

```php
'webhooks' => [
    // Enable global webhook
    'enabled' => env('FORMS_WEBHOOKS_ENABLED', false),

    // Global webhook URL
    'url' => env('FORMS_WEBHOOK_URL'),

    // Secret for webhook signatures
    'secret' => env('FORMS_WEBHOOK_SECRET'),

    // Queue for webhook jobs
    'queue' => env('FORMS_WEBHOOK_QUEUE', 'default'),

    // Request timeout in seconds
    'timeout' => env('FORMS_WEBHOOK_TIMEOUT', 30),

    // Retry configuration
    'retry_times' => 3,
    'retry_backoff' => [10, 60, 300], // seconds between retries
],
```

### Privacy Settings

Configure privacy and PII handling:

```php
'privacy' => [
    // Submission metadata settings
    'submission' => [
        // Include IP address in submissions
        'include_ip' => env('FORMS_INCLUDE_IP', true),

        // Anonymize IP (mask last octet)
        'anonymize_ip' => env('FORMS_ANONYMIZE_IP', false),

        // Include user agent in submissions
        'include_user_agent' => env('FORMS_INCLUDE_USER_AGENT', true),
    ],

    // Consent fields: store the checkbox text and description with each
    // consent (false keeps only the revision hash and time)
    'consent' => [
        'store_text' => env('FORMS_CONSENT_STORE_TEXT', true),
    ],

    // External sharing (webhooks, exports)
    'include_ip_address' => env('FORMS_INCLUDE_IP_ADDRESS', false),
    'include_user_agent' => env('FORMS_WEBHOOK_INCLUDE_USER_AGENT', false),
],
```

### Security Settings

Configure security features:

```php
'security' => [
    // Log security events (honeypot, rate limiting, invalid files)
    'logging_enabled' => env('FORMS_SECURITY_LOGGING', true),
],
```

### Authorization

Configure access control:

```php
'authorization' => [
    // Restrict forms to their creator
    'restrict_by_owner' => env('FORMS_RESTRICT_BY_OWNER', false),

    // Allow admins to bypass ownership checks
    'allow_admin_bypass' => env('FORMS_ALLOW_ADMIN_BYPASS', true),

    // User model class
    'user_model' => env('FORMS_USER_MODEL', 'App\\Models\\User'),
],
```

**Security Note**: The default permissive mode (`restrict_by_owner = false`) allows any authenticated user to access all forms. For multi-user applications, enable ownership enforcement or customize the policies.

### Disk Configuration

Configure the form-uploads filesystem disk:

```php
'disk_config' => [
    'form-uploads' => [
        'driver' => 'local',
        'root' => storage_path('app/form-uploads'),
        'visibility' => 'private',
    ],
],
```

## Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `FORMS_ADMIN_PREFIX` | `admin/forms` | Admin route prefix |
| `FORMS_UPLOADS_DISK` | `form-uploads` | Storage disk for uploads |
| `FORMS_UPLOADS_DIRECTORY` | `uploads` | Upload directory |
| `FORMS_UPLOADS_MAX_SIZE` | `10240` | Max upload size (KB) |
| `FORMS_MEDIA_FOLDER_ID` | `null` | Media library folder for file-field uploads |
| `FORMS_MEDIA_UPLOADED_BY` | `null` | User ID media items are attributed to |
| `FORMS_CHOICE_IMAGES_DISK` | `public` | Disk for image-choice uploads without the media library |
| `FORMS_CHOICE_IMAGES_DIRECTORY` | `forms/choice-images` | Directory for image-choice uploads |
| `FORMS_CHOICE_IMAGES_MAX_SIZE` | `2048` | Max image-choice upload size (KB) |
| `FORMS_RETENTION_DAYS` | `null` | Days to keep submissions |
| `FORMS_CAPTCHA_DRIVER` | `turnstile` | Default CAPTCHA provider |
| `FORMS_TURNSTILE_SITE_KEY` / `FORMS_TURNSTILE_SECRET_KEY` | `null` | Cloudflare Turnstile keys |
| `FORMS_HCAPTCHA_SITE_KEY` / `FORMS_HCAPTCHA_SECRET_KEY` | `null` | hCaptcha keys |
| `FORMS_RECAPTCHA_V2_SITE_KEY` / `FORMS_RECAPTCHA_V2_SECRET_KEY` | `null` | reCAPTCHA v2 keys |
| `FORMS_RECAPTCHA_V3_SITE_KEY` / `FORMS_RECAPTCHA_V3_SECRET_KEY` | `null` | reCAPTCHA v3 keys |
| `FORMS_RECAPTCHA_V3_THRESHOLD` | `0.5` | reCAPTCHA v3 minimum score |
| `FORMS_FROM_NAME` | `null` | Default notification sender name |
| `FORMS_FROM_EMAIL` | `null` | Default notification sender email |
| `FORMS_NOTIFICATION_QUEUE` | `default` | Queue for notifications |
| `FORMS_SHOW_IP_IN_EMAILS` | `true` | Show IP in admin emails |
| `FORMS_WEBHOOKS_ENABLED` | `false` | Enable global webhook |
| `FORMS_WEBHOOK_URL` | `null` | Global webhook URL |
| `FORMS_WEBHOOK_SECRET` | `null` | Webhook signature secret |
| `FORMS_WEBHOOK_QUEUE` | `default` | Queue for webhooks |
| `FORMS_WEBHOOK_TIMEOUT` | `30` | Webhook timeout (seconds) |
| `FORMS_INCLUDE_IP` | `true` | Store IP in submissions |
| `FORMS_ANONYMIZE_IP` | `false` | Anonymize stored IP |
| `FORMS_INCLUDE_USER_AGENT` | `true` | Store user agent |
| `FORMS_CONSENT_STORE_TEXT` | `true` | Store consent text with each consent |
| `FORMS_INCLUDE_IP_ADDRESS` | `false` | Include IP in exports/webhooks |
| `FORMS_SECURITY_LOGGING` | `true` | Log security events |
| `FORMS_RESTRICT_BY_OWNER` | `false` | Enforce ownership |
| `FORMS_ALLOW_ADMIN_BYPASS` | `true` | Allow admin bypass |
| `FORMS_USER_MODEL` | `App\Models\User` | User model class |

## Next Steps

- [Installation Overview](Installation) - Complete installation
- [Quick Start Guide](Getting-Started) - Create your first form
- [Form Builder](Usage-Form-Builder) - Learn the form builder
