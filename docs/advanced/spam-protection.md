---
title: Spam Protection
---

# Spam Protection

Protect your forms from spam submissions and abuse.

## Built-in Protection

The package includes two static spam protection mechanisms plus one opt-in AI agent:

1. **Honeypot Field** - A hidden field that bots typically fill out
2. **Rate Limiting** - Limits submissions per IP address
3. **AI Spam Detection** (v1.2.0+) - Semantic scoring layered on top of the static checks; see [Ai Spam Detection](Ai-Spam-Detection).

The static checks and the AI check are complementary — the AI agent runs **after** the honeypot and rate-limit checks and never replaces them.

## Honeypot Protection

### How It Works

A hidden field is added to forms. Legitimate users don't see it, but bots automatically fill it out. Submissions with this field filled are rejected.

### Configuration

```php
// config/artisanpack/forms.php
'spam_protection' => [
    'honeypot' => [
        'enabled' => true,
        'field_name' => 'website_url', // Appealing name for bots
    ],
],
```

### Implementation

The honeypot field is automatically added to forms:

```blade
{{-- Hidden from users with CSS --}}
<div style="position: absolute; left: -9999px;" aria-hidden="true">
    <label for="website_url">Website (leave blank)</label>
    <input
        type="text"
        id="website_url"
        name="website_url"
        tabindex="-1"
        autocomplete="off"
    >
</div>
```

### Validation

```php
// In FormRenderer component
protected function validateHoneypot(): bool
{
    if (!config('artisanpack.forms.spam_protection.honeypot.enabled')) {
        return true;
    }

    $fieldName = config('artisanpack.forms.spam_protection.honeypot.field_name');

    // If honeypot is filled, it's likely a bot
    if (!empty($this->formData[$fieldName] ?? null)) {
        Log::warning('Honeypot triggered', [
            'form_id' => $this->form->id,
            'ip' => request()->ip(),
        ]);
        return false;
    }

    return true;
}
```

## Rate Limiting

### How It Works

Limits the number of submissions from a single IP address within a time window.

### Configuration

```php
// config/artisanpack/forms.php
'spam_protection' => [
    'rate_limit' => [
        'enabled' => true,
        'attempts' => 5,   // Max submissions
        'decay' => 60,     // Time window in seconds
    ],
],
```

### Implementation

```php
use Illuminate\Support\Facades\RateLimiter;

protected function checkRateLimit(): bool
{
    if (!config('artisanpack.forms.spam_protection.rate_limit.enabled')) {
        return true;
    }

    $key = 'form-submit:' . $this->form->id . ':' . request()->ip();
    $attempts = config('artisanpack.forms.spam_protection.rate_limit.attempts');
    $decay = config('artisanpack.forms.spam_protection.rate_limit.decay');

    if (RateLimiter::tooManyAttempts($key, $attempts)) {
        Log::warning('Rate limit exceeded', [
            'form_id' => $this->form->id,
            'ip' => request()->ip(),
        ]);
        return false;
    }

    RateLimiter::hit($key, $decay);
    return true;
}
```

### User Feedback

When rate limited:

```blade
@if ($rateLimited)
    <div class="error">
        Too many submissions. Please try again later.
    </div>
@endif
```

## reCAPTCHA Integration

For additional protection, integrate Google reCAPTCHA:

### Installation

```bash
composer require google/recaptcha
```

### Configuration

Add to `.env`:

```env
RECAPTCHA_SITE_KEY=your-site-key
RECAPTCHA_SECRET_KEY=your-secret-key
```

### Form Integration

```blade
{{-- In form view --}}
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
```

### Validation

```php
use function addFilter;

addFilter('ap.forms.validationRules', function ($rules, $form) {
    if ($form->settings['recaptcha_enabled'] ?? false) {
        $rules['g-recaptcha-response'] = 'required|recaptcha';
    }
    return $rules;
});

// Custom validation rule
Validator::extend('recaptcha', function ($attribute, $value) {
    $recaptcha = new \ReCaptcha\ReCaptcha(config('services.recaptcha.secret_key'));
    $response = $recaptcha->verify($value, request()->ip());
    return $response->isSuccess();
});
```

## hCaptcha Integration

Alternative to reCAPTCHA:

### Configuration

```env
HCAPTCHA_SITE_KEY=your-site-key
HCAPTCHA_SECRET_KEY=your-secret-key
```

### Form Integration

```blade
<script src="https://js.hcaptcha.com/1/api.js" async defer></script>

<div class="h-captcha" data-sitekey="{{ config('services.hcaptcha.site_key') }}"></div>
```

## Custom Spam Checks

Add custom spam detection:

```php
use function addFilter;

// Check for spam keywords
addFilter('forms.spam_check', function ($isSpam, $form, $data) {
    $spamKeywords = ['casino', 'viagra', 'crypto'];

    foreach ($data as $value) {
        if (is_string($value)) {
            foreach ($spamKeywords as $keyword) {
                if (stripos($value, $keyword) !== false) {
                    return true; // Is spam
                }
            }
        }
    }

    return $isSpam;
});

// Check against spam database
addFilter('forms.spam_check', function ($isSpam, $form, $data) {
    $email = $data['email'] ?? null;

    if ($email && SpamDatabase::isSpammer($email)) {
        return true;
    }

    return $isSpam;
});
```

## Security Logging

Enable logging for security events:

```php
// config/artisanpack/forms.php
'security' => [
    'logging_enabled' => true,
],
```

Logged events:

- Honeypot triggered
- Rate limit exceeded
- Invalid file uploads
- Suspicious submissions

### Log Format

```
[warning] Honeypot triggered {
    "form_id": 1,
    "ip": "192.168.1.100",
    "user_agent": "Mozilla/5.0..."
}
```

## Best Practices

1. **Layer Protection**: Use multiple methods together
2. **Monitor Logs**: Review security logs regularly
3. **Adjust Limits**: Tune rate limits based on legitimate traffic
4. **Update Keywords**: Keep spam keyword lists current
5. **Use HTTPS**: Always serve forms over HTTPS

## Disabling Protection

For testing or trusted environments:

```php
// Disable all spam protection
'spam_protection' => [
    'honeypot' => ['enabled' => false],
    'rate_limit' => ['enabled' => false],
],
```

## Next Steps

- [Ai Spam Detection](Ai-Spam-Detection) - Semantic scoring on top of the static checks
- [Webhooks](Webhooks) - External integrations
- [Customization](Customization) - Extend protection
- [Configuration](Installation-Configuration) - All settings
