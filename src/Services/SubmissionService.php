<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SubmissionService
 *
 * Business logic layer for handling form submissions including
 * validation, file uploads, spam protection, and notification triggering.
 *
 * @since 1.0.0
 */
class SubmissionService
{
    /**
     * The notification service instance.
     */
    protected NotificationService $notificationService;

    /**
     * Create a new submission service instance.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * The minimum time in seconds between form load and submission.
     */
    protected const MIN_SUBMISSION_TIME = 3;

    /**
     * The maximum submissions per IP per minute.
     */
    protected const RATE_LIMIT_MAX_ATTEMPTS = 5;

    /**
     * Create a new submission for a form.
     *
     * Applies the 'forms.submission_data' filter hook to allow
     * third-party packages to modify submission data before saving.
     *
     * @param  array<string, mixed>  $formData  The submitted form data.
     * @param  array<string, UploadedFile|null>  $files  Uploaded files keyed by field name.
     * @param  array<string, mixed>  $metadata  Additional metadata (ip, user_agent, etc.).
     * @return FormSubmission The created submission.
     *
     * @throws \RuntimeException If the submission cannot be created.
     */
    public function create(Form $form, array $formData, array $files = [], array $metadata = []): FormSubmission
    {
        // Apply filter hook to allow modifying submission data before saving
        if (function_exists('applyFilters')) {
            $formData = applyFilters('forms.submission_data', $formData, $form);
        }

        $submission = DB::transaction(function () use ($form, $formData, $files, $metadata): FormSubmission {
            // Create the submission record
            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'page_url' => $metadata['page_url'] ?? null,
                'referrer_url' => $metadata['referrer_url'] ?? null,
                'ip_address' => $metadata['ip_address'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'is_read' => false,
                'is_spam' => false,
                'is_starred' => false,
            ]);

            // Get all form fields
            $fields = $form->fields()->get()->keyBy('name');

            // Save each field value (except file fields which are handled separately)
            foreach ($formData as $fieldName => $value) {
                $field = $fields->get($fieldName);

                if (! $field) {
                    continue;
                }

                // Skip file fields - they're handled separately in handleFileUpload()
                if ($field->type === 'file') {
                    continue;
                }

                $this->saveFieldValue($submission, $field, $value);
            }

            // Handle file uploads
            foreach ($files as $fieldName => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $field = $fields->get($fieldName);

                if (! $field || $field->type !== 'file') {
                    continue;
                }

                $this->handleFileUpload($submission, $field, $file);
            }

            return $submission;
        });

        // Send notifications after transaction completes (outside transaction to ensure submission is persisted)
        $this->sendNotifications($submission);

        return $submission;
    }

    /**
     * Send notifications for a form submission.
     *
     * Queues all active notifications that pass their conditional logic checks.
     *
     * @return int Number of notifications queued
     */
    protected function sendNotifications(FormSubmission $submission): int
    {
        return $this->notificationService->sendNotifications($submission);
    }

    /**
     * Save a field value to the submission.
     */
    protected function saveFieldValue(FormSubmission $submission, FormField $field, mixed $value): FormSubmissionValue
    {
        $data = [
            'submission_id' => $submission->id,
            'field_id' => $field->id,
            'field_name' => $field->name,
            'field_label' => $field->label,
            'field_type' => $field->type,
            'created_at' => now(),
        ];

        // Handle array values (checkbox groups, multi-select)
        if (is_array($value)) {
            $data['value_array'] = $value;
            $data['value'] = null;
        } else {
            $data['value'] = (string) $value;
            $data['value_array'] = null;
        }

        return FormSubmissionValue::create($data);
    }

    /**
     * Handle a file upload for a submission.
     *
     * @throws \InvalidArgumentException If the file extension is not allowed.
     */
    protected function handleFileUpload(FormSubmission $submission, FormField $field, UploadedFile $file): FormUpload
    {
        $disk = config('artisanpack.forms.uploads.disk', 'local');
        $directory = config('artisanpack.forms.uploads.directory', 'form-uploads');

        // Validate extension against allowlist
        $extension = $this->getValidatedExtension($file);

        // Generate a unique filename with validated extension
        $storedName = Str::uuid()->toString().'.'.$extension;
        $storagePath = $directory.'/'.$submission->form_id;

        // Get file size before storing (more reliable for Livewire temp files)
        $fileSize = $file->getSize();

        // Store the file using storeAs (works with both regular UploadedFile and Livewire's TemporaryUploadedFile)
        $path = $file->storeAs($storagePath, $storedName, $disk);

        if ($path === false) {
            throw new \RuntimeException('Unable to store uploaded file.');
        }

        // Fallback: get size from stored file if original size was 0 or false
        if (! $fileSize) {
            $fileSize = Storage::disk($disk)->size($path);
        }

        // Create the upload record
        $upload = FormUpload::create([
            'submission_id' => $submission->id,
            'field_id' => $field->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $fileSize ?: 0,
        ]);

        // Create submission value linking to the upload
        FormSubmissionValue::create([
            'submission_id' => $submission->id,
            'field_id' => $field->id,
            'field_name' => $field->name,
            'field_label' => $field->label,
            'field_type' => $field->type,
            'upload_id' => $upload->id,
            'created_at' => now(),
        ]);

        return $upload;
    }

    /**
     * Get a validated file extension from the uploaded file.
     *
     * Uses server-detected extension when available, falls back to client extension,
     * and validates against configured allowlist.
     *
     * @throws \InvalidArgumentException If the extension is not allowed.
     */
    protected function getValidatedExtension(UploadedFile $file): string
    {
        // Prefer server-detected extension based on MIME type
        $extension = $file->guessExtension();

        // Fall back to client-provided extension if server detection fails
        if ($extension === null || $extension === '') {
            $extension = strtolower($file->getClientOriginalExtension());
        }

        // Sanitize extension (remove any non-alphanumeric characters)
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower($extension));

        if ($extension === null || $extension === '') {
            throw new \InvalidArgumentException('Unable to determine file extension.');
        }

        // Get allowed extensions from config
        $allowedExtensions = config('artisanpack.forms.uploads.allowed_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'zip', 'rar',
        ]);

        // Require explicit null/false to allow any extension (empty array = use defaults)
        if ($allowedExtensions === null || $allowedExtensions === false) {
            return $extension;
        }

        // If empty array, something is misconfigured - fail safely
        if (empty($allowedExtensions)) {
            throw new \InvalidArgumentException(
                'No allowed file extensions configured. Please set artisanpack.forms.uploads.allowed_extensions.'
            );
        }

        // Validate against allowlist
        $allowedExtensions = array_map('strtolower', $allowedExtensions);
        if (! in_array($extension, $allowedExtensions, true)) {
            $this->logSecurityEvent('invalid_file_extension', [
                'attempted_extension' => $extension,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'ip_address' => request()->ip(),
            ]);

            throw new \InvalidArgumentException(
                "File extension '{$extension}' is not allowed. Allowed extensions: ".implode(', ', $allowedExtensions)
            );
        }

        return $extension;
    }

    /**
     * Check if the submission appears to be spam.
     *
     * Logs security events when spam is detected for monitoring and analysis.
     *
     * @param  string|null  $honeypot  The honeypot field value (should be empty).
     * @param  int|null  $formLoadedAt  Timestamp when form was loaded.
     * @param  int|null  $formId  The form ID for logging context.
     * @param  string|null  $ipAddress  The IP address for logging context.
     */
    public function isSpam(?string $honeypot, ?int $formLoadedAt, ?int $formId = null, ?string $ipAddress = null): bool
    {
        // Check honeypot - if filled, it's a bot
        if (! empty($honeypot)) {
            $this->logSecurityEvent('honeypot_triggered', [
                'form_id' => $formId,
                'ip_address' => $ipAddress,
                'honeypot_value' => Str::limit($honeypot, 50),
            ]);

            return true;
        }

        // Check submission time - if too fast, it's a bot
        if ($formLoadedAt !== null) {
            $elapsed = time() - $formLoadedAt;
            if ($elapsed < self::MIN_SUBMISSION_TIME) {
                $this->logSecurityEvent('submission_too_fast', [
                    'form_id' => $formId,
                    'ip_address' => $ipAddress,
                    'elapsed_seconds' => $elapsed,
                    'min_required' => self::MIN_SUBMISSION_TIME,
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Check if the IP address is rate limited.
     *
     * Logs security events when rate limiting is triggered.
     *
     * @param  bool  $logEvent  Whether to log the rate limit event.
     */
    public function isRateLimited(string $ipAddress, int $formId, bool $logEvent = true): bool
    {
        $key = "form-submission:{$formId}:{$ipAddress}";
        $isLimited = RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX_ATTEMPTS);

        if ($isLimited && $logEvent) {
            $this->logSecurityEvent('rate_limit_exceeded', [
                'form_id' => $formId,
                'ip_address' => $ipAddress,
                'max_attempts' => self::RATE_LIMIT_MAX_ATTEMPTS,
            ]);
        }

        return $isLimited;
    }

    /**
     * Record a submission attempt for rate limiting.
     */
    public function recordAttempt(string $ipAddress, int $formId): void
    {
        $key = "form-submission:{$formId}:{$ipAddress}";

        RateLimiter::hit($key, 60);
    }

    /**
     * Log a security-related event.
     *
     * Security events are logged at the warning level with a consistent format
     * for easy filtering and analysis.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logSecurityEvent(string $eventType, array $context = []): void
    {
        if (! config('artisanpack.forms.security.logging_enabled', true)) {
            return;
        }

        Log::warning("[Forms Security] {$eventType}", array_merge([
            'event_type' => $eventType,
            'timestamp' => now()->toIso8601String(),
        ], $context));
    }

    /**
     * Build validation rules for all visible fields.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, FormField>  $fields
     * @param  array<string, bool>  $hiddenFields  Fields hidden by conditional logic.
     * @return array<string, array<int, string>>
     */
    public function buildValidationRules($fields, array $hiddenFields = []): array
    {
        $rules = [];

        foreach ($fields as $field) {
            // Skip fields hidden by conditional logic
            if (isset($hiddenFields[$field->name]) && $hiddenFields[$field->name]) {
                continue;
            }

            // Skip layout-only fields
            if (in_array($field->type, ['heading', 'paragraph', 'divider', 'html'])) {
                continue;
            }

            $fieldRules = $field->buildValidationRules();

            if (! empty($fieldRules)) {
                $rules["formData.{$field->name}"] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Build custom validation messages for fields.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, FormField>  $fields
     * @return array<string, string>
     */
    public function buildValidationMessages($fields): array
    {
        $messages = [];

        foreach ($fields as $field) {
            // Skip layout-only fields
            if (in_array($field->type, ['heading', 'paragraph', 'divider', 'html'])) {
                continue;
            }

            $fieldKey = "formData.{$field->name}";

            // Required message
            if ($field->is_required) {
                $messages["{$fieldKey}.required"] = "The {$field->label} field is required.";
            }

            // Type-specific messages
            if ($field->type === 'email') {
                $messages["{$fieldKey}.email"] = "Please enter a valid email address for {$field->label}.";
            }

            if ($field->type === 'url') {
                $messages["{$fieldKey}.url"] = "Please enter a valid URL for {$field->label}.";
            }

            if ($field->type === 'file') {
                $messages["{$fieldKey}.file"] = "Please upload a valid file for {$field->label}.";
                $messages["{$fieldKey}.mimes"] = "The {$field->label} must be a file of the allowed types.";
                $messages["{$fieldKey}.max"] = "The {$field->label} must not exceed the maximum file size.";
            }
        }

        return $messages;
    }

    /**
     * Get metadata from the current request.
     *
     * Respects privacy configuration settings for IP address collection
     * and anonymization.
     *
     * @return array<string, string|null>
     */
    public function getRequestMetadata(Request $request): array
    {
        $ipAddress = null;

        // Check if IP logging is enabled (nested under submission settings)
        if (config('artisanpack.forms.privacy.submission.include_ip', true)) {
            $ipAddress = $request->ip();

            // Anonymize IP if configured
            if ($ipAddress !== null && config('artisanpack.forms.privacy.submission.anonymize_ip', false)) {
                $ipAddress = $this->anonymizeIpAddress($ipAddress);
            }
        }

        $userAgent = null;
        if (config('artisanpack.forms.privacy.submission.include_user_agent', true)) {
            $userAgent = $request->userAgent();
        }

        return [
            'page_url' => $request->fullUrl(),
            'referrer_url' => $request->header('referer'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];
    }

    /**
     * Anonymize an IP address by masking the last octet (IPv4) or last 80 bits (IPv6).
     *
     * This provides privacy while still allowing for general geographic analysis.
     * Uses inet_pton/inet_ntop for proper handling of compressed IPv6 addresses.
     */
    protected function anonymizeIpAddress(string $ipAddress): string
    {
        // Handle IPv4
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ipAddress) ?? $ipAddress;
        }

        // Handle IPv6 - mask the last 80 bits (10 bytes)
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Convert to binary representation (16 bytes)
            $binary = @inet_pton($ipAddress);

            if ($binary === false) {
                // Fallback if conversion fails
                return $ipAddress;
            }

            // Zero out the last 10 bytes (80 bits) - keep first 6 bytes (48 bits / 3 groups)
            $anonymized = substr($binary, 0, 6).str_repeat("\x00", 10);

            // Convert back to string representation
            $result = @inet_ntop($anonymized);

            if ($result === false) {
                // Fallback if conversion fails
                return $ipAddress;
            }

            return $result;
        }

        return $ipAddress;
    }
}
