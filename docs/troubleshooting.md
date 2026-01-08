---
title: Troubleshooting
---

# Troubleshooting

Solutions to common issues with ArtisanPack UI Forms.

## Installation Issues

### Views not publishing

**Problem**: Views don't publish or changes aren't reflected.

**Solution**:

```bash
# Force publish
php artisan vendor:publish --tag=forms-views --force

# Clear view cache
php artisan view:clear
```

### Migration errors

**Problem**: Migrations fail to run.

**Solution**:

```bash
# Check migration status
php artisan migrate:status

# Verify database connection
php artisan db:show

# Run migrations with verbose output
php artisan migrate -v
```

### Config not loading

**Problem**: Configuration changes not taking effect.

**Solution**:

```bash
# Clear config cache
php artisan config:clear

# Verify config exists
php artisan config:show artisanpack.forms
```

## Form Display Issues

### Form not rendering

**Problem**: Form shows blank or error.

**Solution**:

1. Verify form exists and is active:
```php
$form = Form::where('slug', 'contact')->first();
dd($form->is_active);
```

2. Check for Livewire errors in browser console

3. Ensure Livewire is properly installed:
```bash
php artisan livewire:publish --config
```

### Styles not loading

**Problem**: Form displays without styling.

**Solution**:

1. Ensure CSS is compiled:
```bash
npm run build
```

2. Check Tailwind config includes forms views:
```js
// tailwind.config.js
content: [
    './vendor/artisanpack-ui/forms/resources/views/**/*.blade.php',
]
```

### JavaScript errors

**Problem**: Console shows JavaScript errors.

**Solution**:

1. Ensure Alpine.js is loaded (included with Livewire 3)
2. Check for conflicting scripts
3. Verify `@livewireScripts` is in layout

## Submission Issues

### Form not submitting

**Problem**: Submit button doesn't work.

**Solution**:

1. Check browser console for errors
2. Verify `wire:submit` is on form element
3. Check rate limiting isn't blocking:
```php
// Temporarily disable
'spam_protection' => ['rate_limit' => ['enabled' => false]]
```

### Validation not working

**Problem**: Required fields not validating.

**Solution**:

1. Verify field has `required => true`
2. Check validation rules in component
3. Enable debug mode:
```php
dd($this->rules());
```

### Submissions not saving

**Problem**: Submissions complete but no data stored.

**Solution**:

1. Check database for errors:
```php
DB::enableQueryLog();
// Submit form
dd(DB::getQueryLog());
```

2. Verify `store_submissions` is enabled:
```php
'submissions' => ['store_submissions' => true]
```

## File Upload Issues

### Upload fails silently

**Problem**: File upload doesn't save.

**Solution**:

1. Check PHP upload limits:
```bash
php -i | grep upload_max
php -i | grep post_max
```

2. Verify disk exists:
```php
Storage::disk('form-uploads')->exists('');
```

3. Check permissions on storage directory

### Invalid file type

**Problem**: Valid files rejected.

**Solution**:

1. Verify MIME type is allowed:
```php
'uploads' => [
    'allowed_mimes' => [
        'image/jpeg',
        // Add your MIME type
    ],
],
```

2. Check actual file MIME type:
```php
$file->getMimeType();
```

### Files not downloading

**Problem**: Download links don't work.

**Solution**:

1. Verify file exists:
```php
Storage::disk($upload->disk)->exists($upload->path);
```

2. Check authorization policy
3. Create storage link if using public disk:
```bash
php artisan storage:link
```

## Email Issues

### Notifications not sending

**Problem**: No emails after submission.

**Solution**:

1. Check queue is running:
```bash
php artisan queue:work
```

2. Verify mail configuration:
```bash
php artisan tinker
Mail::raw('Test', fn($m) => $m->to('test@example.com'));
```

3. Check notification is active:
```php
$form->notifications()->where('is_active', true)->get();
```

### Wrong recipient

**Problem**: Emails going to wrong address.

**Solution**:

1. Verify recipient configuration
2. Check if `recipient_type` is `field` vs `static`
3. Verify field name matches

## Performance Issues

### Slow form loading

**Problem**: Forms take long to load.

**Solution**:

1. Enable query caching
2. Eager load relationships:
```php
$form->load('fields', 'steps');
```

3. Check for N+1 queries:
```bash
composer require barryvdh/laravel-debugbar --dev
```

### Queue backing up

**Problem**: Notifications/webhooks delayed.

**Solution**:

1. Run more workers:
```bash
php artisan queue:work --queue=notifications,webhooks
```

2. Use Horizon for queue management
3. Check for failed jobs:
```bash
php artisan queue:failed
```

## Authorization Issues

### Access denied

**Problem**: Users can't access forms.

**Solution**:

1. Check policy:
```php
Gate::inspect('view', $form);
```

2. Verify ownership settings:
```php
'authorization' => [
    'restrict_by_owner' => false, // Permissive mode
]
```

3. Check admin bypass:
```php
'allow_admin_bypass' => true
```

### Can't delete forms

**Problem**: Delete button doesn't work.

**Solution**:

1. Check `delete` policy permission
2. Verify no foreign key constraints
3. Check JavaScript errors

## Webhook Issues

### Webhooks not sending

**Problem**: No webhook requests.

**Solution**:

1. Verify enabled:
```php
'webhooks' => ['enabled' => true]
```

2. Check queue is running
3. Test URL manually:
```bash
curl -X POST https://your-webhook-url
```

### Invalid signature

**Problem**: Receiving endpoint rejects webhook.

**Solution**:

1. Verify secret matches
2. Check signature calculation:
```php
hash_hmac('sha256', $payload, $secret);
```

3. Ensure raw body is used for verification

## Debugging Tips

### Enable debug logging

```php
// In a service provider
Log::debug('Form submitted', [
    'form' => $form->id,
    'data' => $formData,
]);
```

### Check logs

```bash
tail -f storage/logs/laravel.log
```

### Use Laravel Telescope

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Test in isolation

```bash
php artisan tinker
>>> $form = Form::first();
>>> $form->fields;
>>> SubmissionService::create($form, ['email' => 'test@example.com']);
```

## Getting Help

If you can't resolve an issue:

1. Check the [FAQ](./faq.md)
2. Search existing issues on GitLab
3. Create a detailed bug report with:
   - Laravel and package versions
   - Steps to reproduce
   - Error messages
   - Relevant configuration

Contact: [support@artisanpackui.dev](mailto:support@artisanpackui.dev)
