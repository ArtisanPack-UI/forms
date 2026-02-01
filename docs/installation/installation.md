---
title: Installation Overview
---

# Installation Overview

This guide walks you through installing and setting up ArtisanPack UI Forms in your Laravel application.

## Step 1: Install via Composer

```bash
composer require artisanpack-ui/forms
```

## Step 2: Publish Assets

Publish the configuration file, migrations, and views:

```bash
# Publish everything
php artisan vendor:publish --provider="ArtisanPackUI\Forms\FormsServiceProvider"

# Or publish specific assets
php artisan vendor:publish --tag=forms-config
php artisan vendor:publish --tag=forms-migrations
php artisan vendor:publish --tag=forms-views
```

## Step 3: Run Migrations

```bash
php artisan migrate
```

This creates the following database tables:

| Table | Description |
|-------|-------------|
| `forms` | Stores form definitions and settings |
| `form_steps` | Stores multi-step form step definitions |
| `form_fields` | Stores field configurations for each form |
| `form_submissions` | Stores form submission records |
| `form_submission_values` | Stores individual field values per submission |
| `form_uploads` | Stores file upload metadata |
| `form_notifications` | Stores email notification configurations |

## Step 4: Configure Storage Disk

The package creates a `form-uploads` disk for storing file uploads. If you need to customize this, add the following to your `config/filesystems.php`:

```php
'disks' => [
    // ... other disks

    'form-uploads' => [
        'driver' => 'local',
        'root' => storage_path('app/form-uploads'),
        'visibility' => 'private',
    ],
],
```

## Step 5: Configure Environment (Optional)

Add these environment variables to your `.env` file to customize package behavior:

```env
# Admin interface
FORMS_ADMIN_PREFIX=admin/forms

# File uploads
FORMS_UPLOADS_DISK=form-uploads
FORMS_UPLOADS_DIRECTORY=uploads
FORMS_UPLOADS_MAX_SIZE=10240

# Submissions
FORMS_RETENTION_DAYS=

# Notifications
FORMS_FROM_NAME=
FORMS_FROM_EMAIL=
FORMS_NOTIFICATION_QUEUE=default
FORMS_SHOW_IP_IN_EMAILS=true

# Webhooks
FORMS_WEBHOOKS_ENABLED=false
FORMS_WEBHOOK_URL=
FORMS_WEBHOOK_SECRET=
FORMS_WEBHOOK_QUEUE=default
FORMS_WEBHOOK_TIMEOUT=30

# Privacy
FORMS_INCLUDE_IP=true
FORMS_ANONYMIZE_IP=false
FORMS_INCLUDE_USER_AGENT=true

# Security
FORMS_SECURITY_LOGGING=true

# Authorization
FORMS_RESTRICT_BY_OWNER=false
FORMS_ALLOW_ADMIN_BYPASS=true
FORMS_USER_MODEL=App\Models\User
```

## Step 6: Set Up Queue Worker (Recommended)

For optimal performance, notifications and webhooks are queued. Ensure you have a queue worker running:

```bash
php artisan queue:work
```

Or add it to your process manager (Supervisor, etc.).

## Verification

After installation, verify everything is working:

1. Visit `/admin/forms` (or your configured prefix) to access the admin interface
2. Create a test form using the form builder
3. Submit the form and check submissions

## Troubleshooting

### Views Not Publishing

If views don't publish correctly, try:

```bash
php artisan vendor:publish --tag=forms-views --force
```

### Migrations Fail

Ensure your database connection is configured correctly and the database exists:

```bash
php artisan db:show
php artisan migrate:status
```

### Storage Link Issues

If file uploads aren't accessible, create the storage link:

```bash
php artisan storage:link
```

## Next Steps

- [Requirements](Requirements) - Check system requirements
- [Configuration](Configuration) - Customize package settings
- [Quick Start Guide](Getting-Started) - Create your first form
