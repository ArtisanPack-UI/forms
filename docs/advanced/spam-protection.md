---
title: Spam Protection
---

# Spam Protection

Protect your forms from spam submissions and abuse.

## Built-in Protection

The package includes two static spam protection mechanisms, an optional challenge field and one opt-in AI agent:

1. **Honeypot Field** - A hidden field that bots typically fill out
2. **Rate Limiting** - Limits submissions per IP address
3. **CAPTCHA Field** (v1.6.0+) - A Turnstile, hCaptcha or reCAPTCHA challenge you place in a form; see [CAPTCHA](#captcha).
4. **AI Spam Detection** (v1.2.0+) - Semantic scoring layered on top of the static checks; see [Ai Spam Detection](Ai-Spam-Detection).

The static checks and the AI check are complementary — the AI agent runs **after** the honeypot and rate-limit checks and never replaces them. A CAPTCHA field adds to the honeypot and rate limit rather than replacing them.

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

## CAPTCHA

Add a **CAPTCHA** field (Advanced Fields in the palette) where the challenge should appear, for example on the last step of a multi-step form. Four providers are built in:

| Driver | Provider | Sizes |
|--------|----------|-------|
| `turnstile` | Cloudflare Turnstile | normal, compact |
| `hcaptcha` | hCaptcha | normal, compact, invisible |
| `recaptcha_v2` | Google reCAPTCHA v2 (checkbox or invisible) | normal, compact, invisible |
| `recaptcha_v3` | Google reCAPTCHA v3 (score) | invisible |

### Configuration

Set the keys in `.env`. The default driver is used by every CAPTCHA field that does not pick its own **Provider**.

```env
FORMS_CAPTCHA_DRIVER=turnstile

FORMS_TURNSTILE_SITE_KEY=
FORMS_TURNSTILE_SECRET_KEY=

FORMS_HCAPTCHA_SITE_KEY=
FORMS_HCAPTCHA_SECRET_KEY=

FORMS_RECAPTCHA_V2_SITE_KEY=
FORMS_RECAPTCHA_V2_SECRET_KEY=

FORMS_RECAPTCHA_V3_SITE_KEY=
FORMS_RECAPTCHA_V3_SECRET_KEY=
FORMS_RECAPTCHA_V3_THRESHOLD=0.5
```

```php
// config/forms.php
'spam_protection' => [
    'captcha' => [
        'driver'  => env( 'FORMS_CAPTCHA_DRIVER', 'turnstile' ),
        'timeout' => 5, // seconds to wait for the provider
        'drivers' => [
            'turnstile'    => ['site_key' => …, 'secret_key' => …],
            'hcaptcha'     => ['site_key' => …, 'secret_key' => …],
            'recaptcha_v2' => ['site_key' => …, 'secret_key' => …],
            'recaptcha_v3' => ['site_key' => …, 'secret_key' => …, 'score_threshold' => 0.5, 'action' => 'forms_submit'],
        ],
    ],
],
```

reCAPTCHA v2 checkbox and invisible keys are not interchangeable: create the key for the size you use. A provider without both keys renders nothing and checks nothing, so a form keeps working before keys are set (the honeypot and rate limit still apply).

### How It Works

1. The renderer loads the provider's script and renders the widget (Livewire uses the `formsCaptcha` Alpine component; React and Vue use `createCaptchaController()` from the shared helpers). The token is bound to the field's value.
2. Invisible widgets and reCAPTCHA v3 fetch their token when the form is submitted.
3. The server requires a token, then checks it with the provider (`siteverify`) **once**, after every other field has validated and just before the submission is saved. Providers accept a token only once, so it is never checked while moving between steps or when other fields are still invalid.
4. A failed check shows the error on the field and resets the widget (Livewire dispatches `forms-captcha-reset`; the API answers 422 with the error under `data.{field}`). A multi-step Livewire form moves back to the CAPTCHA's step. A provider that cannot be reached fails the check.
5. reCAPTCHA v3 passes when the token's action matches and its score reaches `score_threshold`.
6. The token is never stored, exported, offered as a merge tag or used by conditional logic. Failures are logged as `captcha_failed` security events.

Tokens expire (about two minutes for reCAPTCHA, five for Turnstile), so place the field on the last step of a long multi-step form. Invisible widgets and reCAPTCHA v3 fetch their token when the form is submitted, so in a multi-step form they must be on the last step.

Conditional logic never hides a CAPTCHA field: visibility is worked out from the visitor's own data, which would otherwise let a bot hide the challenge to skip it.

### Custom Drivers

Implement `ArtisanPackUI\Forms\Contracts\CaptchaDriver` (or extend `ArtisanPackUI\Forms\Captcha\SiteVerifyDriver` for a provider with a `siteverify` endpoint) and register it:

```php
addFilter( 'ap.forms.captchaDrivers', function ( array $drivers ): array {
    $drivers['friendly'] = \App\Captcha\FriendlyCaptchaDriver::class;

    return $drivers;
} );
```

The built-in Livewire, React and Vue widgets know the four built-in providers; a custom provider also needs its own field rendering (the `ap.forms.fieldRender` filter in Livewire, or a registered field component in React and Vue).

### Accessibility

CAPTCHAs are a barrier for many people. Prefer **Turnstile** or an **invisible** mode, which usually ask nothing of the visitor, and keep the honeypot on as a fallback.

- **Turnstile** usually passes without interaction. Its widget is keyboard operable and announces its state to screen readers.
- **hCaptcha** offers an accessibility cookie for people who cannot solve visual challenges; its invisible mode only shows a challenge when it suspects a bot.
- **reCAPTCHA v2** falls back to image challenges with an audio alternative, which are hard for many users. The invisible mode shows a challenge only when needed.
- **reCAPTCHA v3** never shows a challenge, but a low score blocks the submission with no way to prove otherwise. Choose the threshold carefully and watch the `captcha_failed` logs. Google requires its badge or a disclosure on the page.

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

## Submission Quarantine

*(since 1.5.0)*

Rather than deleting or hard-rejecting a suspicious submission, you can hold it in **quarantine** for review. This is the contract a spam-quarantine integration writes to: every `FormSubmission` carries a lifecycle `status` and an optional `spam_score`, and quarantined submissions are excluded from the notification pipeline by default.

### Lifecycle columns

Two indexed columns back the workflow:

| Column | Type | Description |
|--------|------|-------------|
| `status` | `SubmissionStatus` | Lifecycle status, defaulting to `Received`. Cast to the enum. |
| `spam_score` | float\|null | Optional score recorded when a submission is quarantined. |

`ArtisanPackUI\Forms\Enums\SubmissionStatus` has three cases — `Received`, `Quarantined`, and `Archived` — each with a translatable `label()`.

### Quarantining and releasing

```php
use ArtisanPackUI\Forms\Enums\SubmissionStatus;
use ArtisanPackUI\Forms\Models\FormSubmission;

// Move a submission into quarantine, optionally recording a spam score
$submission->quarantine(0.97);

// Inspect the status
$submission->isQuarantined(); // true

// Release it back to the received state once reviewed
$submission->release();

// Query by status
FormSubmission::quarantined()->get();
FormSubmission::withStatus(SubmissionStatus::Archived)->get();
```

### Notification suppression

The notification pipeline is suppressed for quarantined submissions by default, so a held submission does not email your team until it is released. Override the decision per submission with the `ap.forms.submission.should_send_notifications` filter:

```php
use function addFilter;

// Always notify, even for quarantined submissions
addFilter('ap.forms.submission.should_send_notifications', function (bool $shouldSend, $submission) {
    return true;
});
```

The filter receives `(bool $shouldSend, FormSubmission $submission)` and must return a boolean. The default value is `false` when the submission is quarantined and `true` otherwise.

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
