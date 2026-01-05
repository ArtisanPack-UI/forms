<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * FormField Model
 *
 * Represents an individual field configuration including type,
 * validation rules, conditional logic, and display settings.
 *
 * @property int $id
 * @property int $form_id
 * @property int|null $step_id
 * @property string $uuid
 * @property string $name
 * @property string $type
 * @property string $label
 * @property string|null $placeholder
 * @property string|null $help_text
 * @property bool $is_required
 * @property array|null $validation_rules
 * @property array|null $field_config
 * @property string|null $default_value
 * @property array|null $conditional_logic
 * @property string $width
 * @property string|null $css_classes
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Form $form
 * @property-read FormStep|null $step
 * @property-read bool $has_conditional_logic
 * @property-read bool $is_file_field
 * @property-read array $options
 * @property-read string $width_class
 *
 * @since 1.0.0
 */
class FormField extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FormFieldFactory
    {
        return FormFieldFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FormField $field): void {
            if (empty($field->uuid)) {
                $field->uuid = Str::uuid()->toString();
            }
        });
    }

    // =========================================
    // Relationships
    // =========================================

    /**
     * Get the form that owns this field.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Get the step that this field belongs to.
     *
     * @return BelongsTo<FormStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(FormStep::class);
    }

    /**
     * Get the submission values for this field.
     *
     * @return HasMany<FormSubmissionValue, $this>
     */
    public function submissionValues(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class, 'field_id');
    }

    // =========================================
    // Scopes
    // =========================================

    /**
     * Scope a query to only include required fields.
     *
     * @param  Builder<FormField>  $query
     * @return Builder<FormField>
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to only include fields of a specific type.
     *
     * @param  Builder<FormField>  $query
     * @return Builder<FormField>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include fields without a step.
     *
     * @param  Builder<FormField>  $query
     * @return Builder<FormField>
     */
    public function scopeWithoutStep(Builder $query): Builder
    {
        return $query->whereNull('step_id');
    }

    /**
     * Scope a query to only include fields in a specific step.
     *
     * @param  Builder<FormField>  $query
     * @return Builder<FormField>
     */
    public function scopeInStep(Builder $query, int $stepId): Builder
    {
        return $query->where('step_id', $stepId);
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Check if this field has conditional logic configured.
     */
    public function getHasConditionalLogicAttribute(): bool
    {
        return ! empty($this->conditional_logic) &&
               ! empty($this->conditional_logic['rules']);
    }

    /**
     * Check if this is a file upload field.
     */
    public function getIsFileFieldAttribute(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Get the options for select/checkbox/radio fields.
     *
     * @return array<int|string, mixed>
     */
    public function getOptionsAttribute(): array
    {
        return $this->field_config['options'] ?? [];
    }

    /**
     * Get the Tailwind CSS width class for this field.
     */
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

    /**
     * Get a config value from the field_config array.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->field_config, $key, $default);
    }

    /**
     * Get a validation rule from the validation_rules array.
     */
    public function getValidationRule(string $key, mixed $default = null): mixed
    {
        return data_get($this->validation_rules, $key, $default);
    }

    /**
     * Build the Laravel validation rules array for this field.
     *
     * @return array<int, string>
     */
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

    /**
     * Get validation rules specific to the field type.
     *
     * @return array<int, string>
     */
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

    /**
     * Get validation rules specific to file uploads.
     *
     * @return array<int, string>
     */
    protected function getFileValidationRules(): array
    {
        $rules = ['file'];

        if ($types = $this->getConfig('allowed_types')) {
            $rules[] = 'mimes:'.implode(',', $types);
        }

        if ($maxSize = $this->getConfig('max_size')) {
            $rules[] = "max:{$maxSize}";
        }

        return $rules;
    }
}
