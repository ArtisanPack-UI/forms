# Models and Relationships

**Purpose:** Define all Eloquent models, their casts, scopes, relationships, and accessors/mutators.

---

## Model Overview

| Model | Table | Purpose |
|-------|-------|---------|
| `Form` | `forms` | Main form definition |
| `FormField` | `form_fields` | Individual field configuration |
| `FormStep` | `form_steps` | Multi-step form pages |
| `FormSubmission` | `form_submissions` | Submission header/metadata |
| `FormSubmissionValue` | `form_submission_values` | Individual submitted values |
| `FormNotification` | `form_notifications` | Email notification configs |
| `FormUpload` | `form_uploads` | File upload metadata |

---

## Model: Form

```php
<?php

namespace ArtisanPackUI\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Form extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'submit_button_text',
        'success_message',
        'redirect_url',
        'settings',
        'is_multi_step',
        'show_progress_bar',
        'allow_step_navigation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_multi_step' => 'boolean',
            'show_progress_bar' => 'boolean',
            'allow_step_navigation' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // =========================================
    // Boot
    // =========================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Form $form) {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name);
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FormStep::class)->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(FormNotification::class)->orderBy('sort_order');
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeMultiStep(Builder $query): Builder
    {
        return $query->where('is_multi_step', true);
    }

    public function scopeSingleStep(Builder $query): Builder
    {
        return $query->where('is_multi_step', false);
    }

    // =========================================
    // Accessors
    // =========================================

    public function getUnreadSubmissionsCountAttribute(): int
    {
        return $this->submissions()->unread()->count();
    }

    public function getTotalSubmissionsCountAttribute(): int
    {
        return $this->submissions()->count();
    }

    public function getActiveNotificationsAttribute(): Collection
    {
        return $this->notifications()->active()->get();
    }

    public function getFieldsOrderedAttribute(): Collection
    {
        if ($this->is_multi_step) {
            return $this->steps()
                ->with(['fields' => fn($q) => $q->orderBy('sort_order')])
                ->get()
                ->flatMap->fields;
        }

        return $this->fields()->orderBy('sort_order')->get();
    }

    // =========================================
    // Methods
    // =========================================

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function duplicate(): self
    {
        $clone = $this->replicate();
        $clone->name = $this->name . ' (Copy)';
        $clone->slug = Str::slug($clone->name);
        $clone->is_active = false;
        $clone->save();

        // Clone steps
        foreach ($this->steps as $step) {
            $newStep = $step->replicate();
            $newStep->form_id = $clone->id;
            $newStep->save();

            // Clone fields in step
            foreach ($step->fields as $field) {
                $newField = $field->replicate();
                $newField->form_id = $clone->id;
                $newField->step_id = $newStep->id;
                $newField->uuid = Str::uuid()->toString();
                $newField->save();
            }
        }

        // Clone fields without steps
        foreach ($this->fields()->whereNull('step_id')->get() as $field) {
            $newField = $field->replicate();
            $newField->form_id = $clone->id;
            $newField->uuid = Str::uuid()->toString();
            $newField->save();
        }

        // Clone notifications
        foreach ($this->notifications as $notification) {
            $newNotification = $notification->replicate();
            $newNotification->form_id = $clone->id;
            $newNotification->save();
        }

        return $clone;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

---

## Model: FormStep

```php
<?php

namespace ArtisanPackUI\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormStep extends Model
{
    protected $fillable = [
        'form_id',
        'title',
        'description',
        'sort_order',
        'next_button_text',
        'prev_button_text',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class, 'step_id')->orderBy('sort_order');
    }

    // =========================================
    // Accessors
    // =========================================

    public function getStepNumberAttribute(): int
    {
        return $this->form->steps()
            ->where('sort_order', '<=', $this->sort_order)
            ->count();
    }

    public function getIsFirstStepAttribute(): bool
    {
        return $this->sort_order === $this->form->steps()->min('sort_order');
    }

    public function getIsLastStepAttribute(): bool
    {
        return $this->sort_order === $this->form->steps()->max('sort_order');
    }

    // =========================================
    // Methods
    // =========================================

    public function getPreviousStep(): ?self
    {
        return $this->form->steps()
            ->where('sort_order', '<', $this->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();
    }

    public function getNextStep(): ?self
    {
        return $this->form->steps()
            ->where('sort_order', '>', $this->sort_order)
            ->orderBy('sort_order')
            ->first();
    }
}
```

---

## Model: FormField

```php
<?php

namespace ArtisanPackUI\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class FormField extends Model
{
    protected $fillable = [
        'form_id',
        'step_id',
        'uuid',
        'name',
        'type',
        'label',
        'placeholder',
        'help_text',
        'is_required',
        'validation_rules',
        'field_config',
        'default_value',
        'conditional_logic',
        'width',
        'css_classes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'validation_rules' => 'array',
            'field_config' => 'array',
            'conditional_logic' => 'array',
        ];
    }

    // =========================================
    // Boot
    // =========================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FormField $field) {
            if (empty($field->uuid)) {
                $field->uuid = Str::uuid()->toString();
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(FormStep::class);
    }

    public function submissionValues(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class, 'field_id');
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeWithoutStep(Builder $query): Builder
    {
        return $query->whereNull('step_id');
    }

    public function scopeInStep(Builder $query, int $stepId): Builder
    {
        return $query->where('step_id', $stepId);
    }

    // =========================================
    // Accessors
    // =========================================

    public function getHasConditionalLogicAttribute(): bool
    {
        return !empty($this->conditional_logic) &&
               !empty($this->conditional_logic['rules']);
    }

    public function getIsFileFieldAttribute(): bool
    {
        return $this->type === 'file';
    }

    public function getOptionsAttribute(): array
    {
        return $this->field_config['options'] ?? [];
    }

    public function getWidthClassAttribute(): string
    {
        return match ($this->width) {
            'half' => 'w-full md:w-1/2',
            'third' => 'w-full md:w-1/3',
            'two-thirds' => 'w-full md:w-2/3',
            default => 'w-full',
        };
    }

    // =========================================
    // Methods
    // =========================================

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->field_config, $key, $default);
    }

    public function getValidationRule(string $key, mixed $default = null): mixed
    {
        return data_get($this->validation_rules, $key, $default);
    }

    public function buildValidationRules(): array
    {
        $rules = [];

        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        $rules = array_merge($rules, $this->getTypeValidationRules());

        // Custom validation rules
        if ($min = $this->getValidationRule('min')) {
            $rules[] = "min:{$min}";
        }

        if ($max = $this->getValidationRule('max')) {
            $rules[] = "max:{$max}";
        }

        if ($pattern = $this->getValidationRule('pattern')) {
            $rules[] = "regex:{$pattern}";
        }

        return $rules;
    }

    protected function getTypeValidationRules(): array
    {
        return match ($this->type) {
            'email' => ['email'],
            'url' => ['url'],
            'number' => ['numeric'],
            'date' => ['date'],
            'file' => $this->getFileValidationRules(),
            'checkbox_group', 'select_multiple' => ['array'],
            default => [],
        };
    }

    protected function getFileValidationRules(): array
    {
        $rules = ['file'];

        if ($types = $this->getConfig('allowed_types')) {
            $rules[] = 'mimes:' . implode(',', $types);
        }

        if ($maxSize = $this->getConfig('max_size')) {
            $rules[] = "max:{$maxSize}";
        }

        return $rules;
    }
}
```

---

## Model: FormSubmission

```php
<?php

namespace ArtisanPackUI\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'submission_number',
        'page_url',
        'referrer_url',
        'ip_address',
        'user_agent',
        'is_read',
        'is_spam',
        'is_starred',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_spam' => 'boolean',
            'is_starred' => 'boolean',
        ];
    }

    // =========================================
    // Boot
    // =========================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FormSubmission $submission) {
            if (empty($submission->submission_number)) {
                $submission->submission_number = self::generateSubmissionNumber($submission->form_id);
            }
        });
    }

    protected static function generateSubmissionNumber(int $formId): string
    {
        $count = self::where('form_id', $formId)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        return sprintf(
            'FORM-%d-%s-%05d',
            $formId,
            now()->format('Y'),
            $count
        );
    }

    // =========================================
    // Relationships
    // =========================================

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class, 'submission_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(FormUpload::class, 'submission_id');
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->where('is_read', true);
    }

    public function scopeNotSpam(Builder $query): Builder
    {
        return $query->where('is_spam', false);
    }

    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('is_spam', true);
    }

    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // =========================================
    // Accessors
    // =========================================

    public function getDataAttribute(): Collection
    {
        return $this->values->mapWithKeys(function (FormSubmissionValue $value) {
            return [$value->field_name => $value->display_value];
        });
    }

    public function getDataArrayAttribute(): array
    {
        return $this->data->toArray();
    }

    // =========================================
    // Methods
    // =========================================

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    public function markAsUnread(): void
    {
        $this->update(['is_read' => false]);
    }

    public function toggleSpam(): void
    {
        $this->update(['is_spam' => !$this->is_spam]);
    }

    public function toggleStar(): void
    {
        $this->update(['is_starred' => !$this->is_starred]);
    }

    public function getValue(string $fieldName): ?string
    {
        return $this->values
            ->firstWhere('field_name', $fieldName)
            ?->display_value;
    }

    public function getEmailValue(): ?string
    {
        // Try to find an email field
        $emailValue = $this->values->first(function ($value) {
            return $value->field_type === 'email';
        });

        return $emailValue?->value;
    }
}
```

---

## Model: FormSubmissionValue

```php
<?php

namespace ArtisanPackUI\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionValue extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'field_id',
        'field_name',
        'field_label',
        'field_type',
        'value',
        'value_array',
        'upload_id',
    ];

    protected function casts(): array
    {
        return [
            'value_array' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // =========================================
    // Relationships
    // =========================================

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'field_id');
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(FormUpload::class, 'upload_id');
    }

    // =========================================
    // Accessors
    // =========================================

    public function getDisplayValueAttribute(): string
    {
        // Handle array values (checkbox groups, multi-select)
        if (!empty($this->value_array)) {
            return implode(', ', $this->value_array);
        }

        // Handle file uploads
        if ($this->upload_id && $this->upload) {
            return $this->upload->original_name;
        }

        return $this->value ?? '';
    }

    public function getIsArrayValueAttribute(): bool
    {
        return !empty($this->value_array);
    }

    public function getIsFileAttribute(): bool
    {
        return $this->field_type === 'file' && $this->upload_id !== null;
    }
}
```

---

## Model: FormNotification

```php
<?php

namespace ArtisanPackUI\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class FormNotification extends Model
{
    protected $fillable = [
        'form_id',
        'type',
        'name',
        'to_email',
        'to_field',
        'cc_emails',
        'bcc_emails',
        'reply_to_email',
        'reply_to_field',
        'from_name',
        'from_email',
        'subject',
        'message',
        'conditional_logic',
        'include_submission_data',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'conditional_logic' => 'array',
            'include_submission_data' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // =========================================
    // Constants
    // =========================================

    public const TYPE_ADMIN = 'admin';
    public const TYPE_AUTORESPONDER = 'autoresponder';
    public const TYPE_CUSTOM = 'custom';

    // =========================================
    // Relationships
    // =========================================

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    // =========================================
    // Scopes
    // =========================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeAdmin(Builder $query): Builder
    {
        return $query->ofType(self::TYPE_ADMIN);
    }

    public function scopeAutoresponder(Builder $query): Builder
    {
        return $query->ofType(self::TYPE_AUTORESPONDER);
    }

    // =========================================
    // Accessors
    // =========================================

    public function getHasConditionalLogicAttribute(): bool
    {
        return !empty($this->conditional_logic) &&
               !empty($this->conditional_logic['rules']);
    }

    public function getIsAutoresponderAttribute(): bool
    {
        return $this->type === self::TYPE_AUTORESPONDER;
    }

    // =========================================
    // Methods
    // =========================================

    public function getRecipientEmails(FormSubmission $submission): array
    {
        $emails = [];

        // Static emails
        if ($this->to_email) {
            $emails = array_merge($emails, array_map('trim', explode(',', $this->to_email)));
        }

        // Dynamic email from field
        if ($this->to_field) {
            $fieldEmail = $submission->getValue($this->to_field);
            if ($fieldEmail && filter_var($fieldEmail, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $fieldEmail;
            }
        }

        return array_unique(array_filter($emails));
    }

    public function getReplyToEmail(FormSubmission $submission): ?string
    {
        if ($this->reply_to_field) {
            return $submission->getValue($this->reply_to_field);
        }

        return $this->reply_to_email;
    }

    public function parseMessage(FormSubmission $submission): string
    {
        $message = $this->message;

        // Replace {field_name} placeholders
        foreach ($submission->values as $value) {
            $placeholder = '{' . $value->field_name . '}';
            $message = str_replace($placeholder, $value->display_value, $message);
        }

        // Replace system placeholders
        $message = str_replace('{submission_date}', $submission->created_at->format('F j, Y'), $message);
        $message = str_replace('{submission_time}', $submission->created_at->format('g:i A'), $message);
        $message = str_replace('{submission_number}', $submission->submission_number, $message);
        $message = str_replace('{form_name}', $submission->form->name, $message);

        return $message;
    }

    public function parseSubject(FormSubmission $submission): string
    {
        $subject = $this->subject;

        // Same placeholder replacement as message
        foreach ($submission->values as $value) {
            $placeholder = '{' . $value->field_name . '}';
            $subject = str_replace($placeholder, $value->display_value, $subject);
        }

        $subject = str_replace('{form_name}', $submission->form->name, $subject);
        $subject = str_replace('{submission_number}', $submission->submission_number, $subject);

        return $subject;
    }
}
```

---

## Model: FormUpload

```php
<?php

namespace ArtisanPackUI\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FormUpload extends Model
{
    protected $fillable = [
        'submission_id',
        'field_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'field_id');
    }

    // =========================================
    // Accessors
    // =========================================

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    // =========================================
    // Methods
    // =========================================

    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk($this->disk)->download(
            $this->path,
            $this->original_name
        );
    }

    public function delete(): bool
    {
        // Delete file from storage
        Storage::disk($this->disk)->delete($this->path);

        // Delete database record
        return parent::delete();
    }
}
```

---

## Model Factory Summary

Each model should have a corresponding factory for testing:

| Factory | Key Attributes |
|---------|----------------|
| `FormFactory` | name, slug, is_active |
| `FormFieldFactory` | form_id, type, label, name |
| `FormStepFactory` | form_id, title, sort_order |
| `FormSubmissionFactory` | form_id, submission_number |
| `FormSubmissionValueFactory` | submission_id, field_name, value |
| `FormNotificationFactory` | form_id, type, name, subject |
| `FormUploadFactory` | submission_id, original_name, path |

See [11-testing-strategy.md](11-testing-strategy.md) for factory implementations.

---

## Related Documents

- [01-database-schema.md](01-database-schema.md) - Database structure these models represent
- [05-field-types.md](05-field-types.md) - Field type configurations
- [06-conditional-logic.md](06-conditional-logic.md) - Conditional logic JSON handling
- [08-notifications.md](08-notifications.md) - Notification model usage
