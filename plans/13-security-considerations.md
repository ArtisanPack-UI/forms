# Security Considerations

**Purpose:** Define security best practices, input sanitization, output escaping, and protection mechanisms for the forms package.

---

## Overview

Form handling is a critical security surface. This document outlines:

- Input validation and sanitization
- Output escaping
- CSRF protection
- Rate limiting and spam protection
- File upload security
- SQL injection prevention
- XSS prevention
- Data privacy considerations

---

## Input Validation

### Server-Side Validation (Required)

**Never trust client-side validation alone.** All form input must be validated on the server.

```php
// In FormRenderer component
protected function buildAllValidationRules(): array
{
    $rules = [];

    foreach ($this->form->fields_ordered as $field) {
        if (!$this->evaluateConditionalLogic($field)) {
            continue;
        }

        $fieldRules = $field->buildValidationRules();
        $rules["formData.{$field->name}"] = $fieldRules;
    }

    return $rules;
}

// Always validate before processing
$this->validate(
    $this->buildAllValidationRules(),
    $this->getCustomMessages()
);
```

### Type-Specific Validation

Each field type must have appropriate validation rules:

```php
protected function getTypeValidationRules(): array
{
    return match ($this->type) {
        'email' => ['email:rfc,dns'],
        'url' => ['url'],
        'number' => ['numeric'],
        'phone' => ['regex:/^[\d\s\-\+\(\)]+$/'],
        'date' => ['date', 'date_format:Y-m-d'],
        'file' => $this->getFileValidationRules(),
        'select', 'radio' => ['in:' . implode(',', array_column($this->options, 'value'))],
        'checkbox_group' => ['array'],
        default => [],
    };
}
```

---

## Input Sanitization

### Use artisanpack-ui/security Package

The forms package should integrate with `artisanpack-ui/security` for input sanitization:

```php
use function ArtisanPackUI\Security\sanitizeText;
use function ArtisanPackUI\Security\sanitizeEmail;
use function ArtisanPackUI\Security\sanitizeUrl;

class SubmissionService
{
    public function sanitizeSubmissionData(array $data, Form $form): array
    {
        $sanitized = [];

        foreach ($form->fields as $field) {
            $value = $data[$field->name] ?? null;

            if ($value === null) {
                $sanitized[$field->name] = null;
                continue;
            }

            $sanitized[$field->name] = match ($field->type) {
                'email' => sanitizeEmail($value),
                'url' => sanitizeUrl($value),
                'number' => (float) $value,
                'checkbox' => (bool) $value,
                'checkbox_group' => array_map('sanitizeText', (array) $value),
                'html' => $this->sanitizeHtml($value), // Use kses()
                default => sanitizeText($value),
            };
        }

        return $sanitized;
    }

    protected function sanitizeHtml(string $value): string
    {
        if (function_exists('kses')) {
            return kses($value);
        }

        return strip_tags($value);
    }
}
```

### Field Name Sanitization

Field names are used in database queries and must be sanitized:

```php
public function generateFieldName(string $type): string
{
    $baseName = Str::snake($type);
    $baseName = preg_replace('/[^a-z0-9_]/', '', $baseName);

    if (empty($baseName)) {
        $baseName = 'field';
    }

    return $baseName;
}
```

---

## Output Escaping

### Always Escape User Content

**Every** user-provided value must be escaped before display:

```blade
{{-- CORRECT: Use {{ }} for automatic escaping --}}
<span>{{ $submission->getValue('name') }}</span>

{{-- INCORRECT: Never use {!! !!} for user content --}}
<span>{!! $submission->getValue('name') !!}</span>
```

### Controlled HTML Output

For fields that allow HTML (like paragraph), use sanitization:

```blade
{{-- Use kses() or similar for controlled HTML --}}
<div class="prose">
    {!! escHtml($field->help_text) !!}
</div>
```

### Email Templates

Email content must be escaped:

```blade
{{-- In notification email template --}}
<p>{{ __( 'forms.email.greeting' ) }}</p>

@foreach($submission->values as $value)
    <tr>
        <td>{{ $value->field_label }}</td>
        <td>{{ $value->display_value }}</td>
    </tr>
@endforeach
```

---

## CSRF Protection

### Form Submissions

All Livewire forms automatically include CSRF protection. For non-Livewire implementations:

```blade
<form method="POST" action="{{ route('forms.submit', $form) }}">
    @csrf
    {{-- Form fields --}}
</form>
```

### API Endpoints

API endpoints must use Sanctum or similar token authentication:

```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/forms/{form}/submit', [SubmissionController::class, 'store']);
});
```

---

## Rate Limiting

### Submission Rate Limiting

Prevent form abuse with rate limiting:

```php
public function submit(): void
{
    $key = 'form_submission:' . request()->ip() . ':' . $this->form->id;
    $maxAttempts = config('forms.spam_protection.rate_limit.max_attempts', 5);
    $decayMinutes = config('forms.spam_protection.rate_limit.decay_minutes', 1);

    if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
        $this->addError('form', __('Too many submissions. Please try again later.'));
        return;
    }

    RateLimiter::hit($key, $decayMinutes * 60);

    // Process submission...
}
```

### Builder Rate Limiting

Also limit form builder operations:

```php
// FormBuilder middleware
RateLimiter::for('form-builder', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

---

## Spam Protection

### Honeypot Field

Hidden field that bots typically fill out:

```blade
{{-- Hidden from humans via CSS --}}
<div class="hidden" aria-hidden="true" style="position: absolute; left: -9999px;">
    <input
        type="text"
        name="website_url"
        wire:model="honeypot"
        tabindex="-1"
        autocomplete="off"
    />
</div>
```

```php
// Check in submit handler
if (!empty($this->honeypot)) {
    // Log potential bot attempt
    Log::info('Honeypot triggered', [
        'form_id' => $this->form->id,
        'ip' => request()->ip(),
    ]);

    // Silently succeed (don't reveal to bot)
    $this->isSubmitted = true;
    return;
}
```

### Timestamp Validation

Reject submissions that are too fast:

```php
public function mount(Form $form): void
{
    $this->form = $form;
    $this->formLoadedAt = now()->timestamp;
}

public function submit(): void
{
    $minimumSeconds = 3; // Minimum time to fill form

    if (now()->timestamp - $this->formLoadedAt < $minimumSeconds) {
        $this->addError('form', __('Please take your time filling out the form.'));
        return;
    }

    // Process submission...
}
```

---

## File Upload Security

### Validation

Strict file validation is critical:

```php
protected function getFileValidationRules(): array
{
    $field = $this->field;

    return [
        'file',
        'max:' . $field->getConfig('max_size', config('forms.uploads.max_size', 10240)),
        'mimes:' . implode(',', $this->getAllowedMimes()),
    ];
}

protected function getAllowedMimes(): array
{
    $configuredTypes = $this->field->getConfig('allowed_types', []);
    $defaultTypes = config('forms.uploads.allowed_types', []);

    $allowedTypes = !empty($configuredTypes) ? $configuredTypes : $defaultTypes;

    // Never allow executable extensions
    $dangerousExtensions = ['php', 'phar', 'exe', 'bat', 'sh', 'js', 'html', 'htm'];
    return array_diff($allowedTypes, $dangerousExtensions);
}
```

### Secure Storage

Store uploads outside the web root:

```php
// config/filesystems.php
'form-uploads' => [
    'driver' => 'local',
    'root' => storage_path('app/form-uploads'),
    'visibility' => 'private', // Not publicly accessible
],
```

### File Naming

Never use user-provided filenames:

```php
public function storeFile(UploadedFile $file, FormSubmission $submission): string
{
    $extension = $file->getClientOriginalExtension();

    // Validate extension again
    if (!in_array(strtolower($extension), $this->getAllowedMimes())) {
        throw new InvalidFileTypeException();
    }

    // Generate safe filename
    $filename = sprintf(
        '%s_%s_%s.%s',
        $submission->id,
        Str::uuid()->toString(),
        time(),
        $extension
    );

    return $file->storeAs(
        'submissions/' . $submission->id,
        $filename,
        'form-uploads'
    );
}
```

### Secure Downloads

Authorize file downloads:

```php
// SubmissionController.php
public function downloadFile(FormSubmission $submission, FormUpload $upload)
{
    $this->authorize('view', $submission);

    if ($upload->submission_id !== $submission->id) {
        abort(403);
    }

    return Storage::disk('form-uploads')
        ->download($upload->file_path, $upload->original_filename);
}
```

---

## SQL Injection Prevention

### Use Eloquent/Query Builder

Always use Eloquent or Query Builder, never raw queries with user input:

```php
// CORRECT: Using Eloquent
$submissions = FormSubmission::where('form_id', $formId)
    ->where('created_at', '>=', $startDate)
    ->get();

// CORRECT: Using Query Builder with bindings
$results = DB::table('form_submissions')
    ->where('form_id', '?')
    ->setBindings([$formId])
    ->get();

// INCORRECT: Never interpolate user input into queries
$results = DB::select("SELECT * FROM form_submissions WHERE form_id = {$formId}");
```

### Dynamic Column Names

When using dynamic column names, validate against whitelist:

```php
public function sortBy(string $column, string $direction): void
{
    $allowedColumns = ['created_at', 'submission_number', 'status'];
    $allowedDirections = ['asc', 'desc'];

    if (!in_array($column, $allowedColumns)) {
        $column = 'created_at';
    }

    if (!in_array($direction, $allowedDirections)) {
        $direction = 'desc';
    }

    $this->sortColumn = $column;
    $this->sortDirection = $direction;
}
```

---

## XSS Prevention

### Blade Escaping

Blade's `{{ }}` syntax automatically escapes output:

```blade
{{-- Safe: auto-escaped --}}
{{ $submission->getValue('name') }}

{{-- DANGER: unescaped - only use for trusted content --}}
{!! $trustedContent !!}
```

### JavaScript Context

When passing data to JavaScript, use JSON encoding:

```blade
<script>
    // CORRECT: JSON encoding escapes special characters
    const formData = @json($formData);

    // INCORRECT: Direct interpolation is vulnerable
    const formData = '{{ $formData }}';
</script>
```

### Alpine.js

Alpine.js expressions are safe, but be careful with x-html:

```blade
{{-- Safe: Alpine text binding escapes content --}}
<span x-text="submission.name"></span>

{{-- DANGER: x-html does not escape --}}
<div x-html="submission.htmlContent"></div>
```

---

## Authorization

### Form Access Control

Verify user can access forms:

```php
// FormPolicy.php
class FormPolicy
{
    public function view(User $user, Form $form): bool
    {
        // Public forms can be viewed by anyone
        if ($form->is_published) {
            return true;
        }

        // Draft forms only by creator/admin
        return $user->id === $form->user_id || $user->hasRole('admin');
    }

    public function update(User $user, Form $form): bool
    {
        return $user->id === $form->user_id || $user->hasRole('admin');
    }

    public function viewSubmissions(User $user, Form $form): bool
    {
        return $user->id === $form->user_id || $user->hasRole('admin');
    }
}
```

### Submission Access Control

```php
// SubmissionPolicy.php
class SubmissionPolicy
{
    public function view(User $user, FormSubmission $submission): bool
    {
        return $user->id === $submission->form->user_id
            || $user->hasRole('admin');
    }

    public function export(User $user, Form $form): bool
    {
        return $user->id === $form->user_id || $user->hasRole('admin');
    }
}
```

---

## Data Privacy

### IP Address Handling

Make IP logging configurable:

```php
// config/forms.php
'privacy' => [
    'log_ip_address' => env('FORMS_LOG_IP', true),
    'ip_anonymize' => env('FORMS_ANONYMIZE_IP', false),
],
```

```php
// In SubmissionService
$ipAddress = null;

if (config('forms.privacy.log_ip_address')) {
    $ipAddress = request()->ip();

    if (config('forms.privacy.ip_anonymize')) {
        // Anonymize last octet for IPv4
        $ipAddress = preg_replace('/\.\d+$/', '.0', $ipAddress);
    }
}
```

### Data Retention

Implement automatic data purging:

```php
// PruneSubmissionsCommand.php
class PruneSubmissionsCommand extends Command
{
    protected $signature = 'forms:prune-submissions';

    public function handle(): void
    {
        $retentionDays = config('forms.submissions.retention_days');

        if (!$retentionDays) {
            return; // Retention disabled
        }

        $cutoffDate = now()->subDays($retentionDays);

        FormSubmission::where('created_at', '<', $cutoffDate)
            ->each(function (FormSubmission $submission) {
                // Delete associated files
                foreach ($submission->uploads as $upload) {
                    Storage::disk('form-uploads')->delete($upload->file_path);
                }

                $submission->delete();
            });
    }
}
```

### Export Security

Secure CSV exports:

```php
public function export(Form $form): StreamedResponse
{
    $this->authorize('export', $form);

    $filename = sprintf(
        '%s_submissions_%s.csv',
        Str::slug($form->name),
        now()->format('Y-m-d_His')
    );

    return response()->streamDownload(function () use ($form) {
        $handle = fopen('php://output', 'w');

        // Headers
        $headers = $this->getExportHeaders($form);
        fputcsv($handle, $headers);

        // Data
        $form->submissions()->chunk(100, function ($submissions) use ($handle, $form) {
            foreach ($submissions as $submission) {
                $row = $this->formatSubmissionRow($submission, $form);
                fputcsv($handle, $row);
            }
        });

        fclose($handle);
    }, $filename, [
        'Content-Type' => 'text/csv',
    ]);
}
```

---

## Logging and Monitoring

### Security Event Logging

Log security-relevant events:

```php
// Log suspicious activity
Log::warning('Potential form abuse', [
    'form_id' => $form->id,
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'reason' => 'rate_limit_exceeded',
]);

// Log successful submissions (configurable)
if (config('forms.logging.log_submissions')) {
    Log::info('Form submission received', [
        'form_id' => $form->id,
        'submission_id' => $submission->id,
    ]);
}
```

---

## Security Checklist

Before deploying forms in production, verify:

- [ ] All user inputs are validated server-side
- [ ] All outputs are properly escaped
- [ ] CSRF protection is enabled
- [ ] Rate limiting is configured
- [ ] Honeypot protection is enabled
- [ ] File uploads validate type and size
- [ ] Files are stored outside web root
- [ ] SQL queries use parameterized bindings
- [ ] Authorization policies are in place
- [ ] Sensitive data logging is appropriate
- [ ] Data retention policy is implemented
- [ ] Export functionality is authorized

---

## Related Documents

- [04-form-renderer.md](04-form-renderer.md) - Submission handling
- [11-testing-strategy.md](11-testing-strategy.md) - Security test coverage
- [12-implementation-order.md](12-implementation-order.md) - Phase 12 security audit
