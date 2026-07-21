<?php

/**
 * Form field model.
 *
 * Represents an individual field configuration including type,
 * validation rules, conditional logic, and display settings.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Models;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Form field model class.
 *
 * Represents an individual field configuration including type,
 * validation rules, conditional logic, and display settings.
 *
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
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

    // =========================================
    // Relationships
    // =========================================

    /**
     * Gets the form that owns this field.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<Form, $this> The form relationship.
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo( Form::class );
    }

    /**
     * Gets the step that this field belongs to.
     *
     * @since 1.0.0
     *
     * @return BelongsTo<FormStep, $this> The step relationship.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo( FormStep::class );
    }

    /**
     * Gets the submission values for this field.
     *
     * @since 1.0.0
     *
     * @return HasMany<FormSubmissionValue, $this> The submission values relationship.
     */
    public function submissionValues(): HasMany
    {
        return $this->hasMany( FormSubmissionValue::class, 'field_id' );
    }

    // =========================================
    // Scopes
    // =========================================

    /**
     * Scopes a query to only include required fields.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormField>  $query  The query builder instance.
     *
     * @return Builder<FormField> The modified query builder.
     */
    public function scopeRequired( Builder $query ): Builder
    {
        return $query->where( 'is_required', true );
    }

    /**
     * Scopes a query to only include fields of a specific type.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormField>  $query  The query builder instance.
     * @param  string  $type  The field type to filter by.
     *
     * @return Builder<FormField> The modified query builder.
     */
    public function scopeOfType( Builder $query, string $type ): Builder
    {
        return $query->where( 'type', $type );
    }

    /**
     * Scopes a query to only include fields without a step.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormField>  $query  The query builder instance.
     *
     * @return Builder<FormField> The modified query builder.
     */
    public function scopeWithoutStep( Builder $query ): Builder
    {
        return $query->whereNull( 'step_id' );
    }

    /**
     * Scopes a query to only include fields in a specific step.
     *
     * @since 1.0.0
     *
     * @param  Builder<FormField>  $query  The query builder instance.
     * @param  int  $stepId  The step ID to filter by.
     *
     * @return Builder<FormField> The modified query builder.
     */
    public function scopeInStep( Builder $query, int $stepId ): Builder
    {
        return $query->where( 'step_id', $stepId );
    }

    // =========================================
    // Accessors
    // =========================================

    /**
     * Checks if this field has conditional logic configured.
     *
     * @since 1.0.0
     *
     * @return bool True if conditional logic is configured.
     */
    public function getHasConditionalLogicAttribute(): bool
    {
        return ! empty( $this->conditional_logic ) &&
               ! empty( $this->conditional_logic['rules'] );
    }

    /**
     * Checks if this is a file upload field.
     *
     * @since 1.0.0
     *
     * @return bool True if this is a file field.
     */
    public function getIsFileFieldAttribute(): bool
    {
        return 'file' === $this->type;
    }

    /**
     * Gets the options for select/checkbox/radio fields.
     *
     * @since 1.0.0
     *
     * @return array<int|string, mixed> The field options array.
     */
    public function getOptionsAttribute(): array
    {
        return $this->field_config['options'] ?? [];
    }

    /**
     * Gets the Tailwind CSS width class for this field.
     *
     * @since 1.0.0
     *
     * @return string The CSS width class.
     */
    public function getWidthClassAttribute(): string
    {
        return match ( $this->width ) {
            'half'       => 'w-full md:w-1/2',
            'third'      => 'w-full md:w-1/3',
            'two-thirds' => 'w-full md:w-2/3',
            default      => 'w-full',
        };
    }

    // =========================================
    // Methods
    // =========================================

    /**
     * Gets a config value from the field_config array.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The config key using dot notation.
     * @param  mixed  $default  The default value if key not found.
     *
     * @return mixed The config value or default.
     */
    public function getConfig( string $key, mixed $default = null ): mixed
    {
        return data_get( $this->field_config, $key, $default );
    }

    /**
     * Gets a validation rule from the validation_rules array.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The validation rule key.
     * @param  mixed  $default  The default value if key not found.
     *
     * @return mixed The validation rule or default.
     */
    public function getValidationRule( string $key, mixed $default = null ): mixed
    {
        return data_get( $this->validation_rules, $key, $default );
    }

    /**
     * Builds the Laravel validation rules array for this field.
     *
     * Applies the 'ap.forms.validationRules' filter hook to allow
     * third-party packages to modify validation rules for a field.
     *
     * @since 1.0.0
     *
     * @return array<int, string> The validation rules array.
     */
    public function buildValidationRules(): array
    {
        // Layout fields don't need validation
        if ( $this->isLayoutField() ) {
            return [];
        }

        $rules = [];

        if ( $this->is_required ) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        $rules = array_merge( $rules, $this->getTypeValidationRules() );

        // Custom validation rules
        if ( $min = $this->getValidationRule( 'min' ) ) {
            $rules[] = "min:{$min}";
        }

        if ( $max = $this->getValidationRule( 'max' ) ) {
            $rules[] = "max:{$max}";
        }

        if ( $pattern = $this->getValidationRule( 'pattern' ) ) {
            $rules[] = "regex:{$pattern}";
        }

        // Date range validation
        if ( $minDate = $this->getValidationRule( 'min_date' ) ) {
            $rules[] = "after_or_equal:{$minDate}";
        }

        if ( $maxDate = $this->getValidationRule( 'max_date' ) ) {
            $rules[] = "before_or_equal:{$maxDate}";
        }

        // Time range validation
        if ( $minTime = $this->getValidationRule( 'min_time' ) ) {
            $rules[] = "after_or_equal:{$minTime}";
        }

        if ( $maxTime = $this->getValidationRule( 'max_time' ) ) {
            $rules[] = "before_or_equal:{$maxTime}";
        }

        // Apply filter hook for extensibility
        $rules = applyFilters( 'ap.forms.validationRules', $rules, $this );

        return $rules;
    }

    /**
     * Checks if this field is a layout-only field (no data input).
     *
     * Delegates to FieldTypes::isLayoutField() to use the central config-driven logic.
     *
     * @since 1.0.0
     *
     * @return bool True if this is a layout-only field.
     */
    public function isLayoutField(): bool
    {
        return FieldTypes::isLayoutField( $this->type );
    }

    /**
     * Creates a new factory instance for the model.
     *
     * @since 1.0.0
     *
     * @return FormFieldFactory The factory instance.
     */
    protected static function newFactory(): FormFieldFactory
    {
        return FormFieldFactory::new();
    }

    /**
     * Gets the attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The cast definitions.
     */
    protected function casts(): array
    {
        return [
            'is_required'       => 'boolean',
            'validation_rules'  => 'array',
            'field_config'      => 'array',
            'conditional_logic' => 'array',
        ];
    }

    // =========================================
    // Boot
    // =========================================

    /**
     * Bootstraps the model and its traits.
     *
     * Sets up model event listeners to auto-generate UUIDs on creation.
     *
     * @since 1.0.0
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating( function ( FormField $field ): void {
            if ( empty( $field->uuid ) ) {
                $field->uuid = Str::uuid()->toString();
            }
        } );
    }

    /**
     * Gets validation rules specific to the field type.
     *
     * Supports custom field types registered via the 'ap.forms.fieldTypes'
     * filter hook by checking for a 'type_validation' key in the field type config.
     *
     * @since 1.0.0
     *
     * @return array<int, string> The type-specific validation rules.
     */
    protected function getTypeValidationRules(): array
    {
        // Check for built-in types first
        $builtInRules = match ( $this->type ) {
            'email'                             => ['email'],
            'url'                               => ['url'],
            'number'                            => ['numeric'],
            'phone'                             => ['string'],
            'date'                              => ['date'],
            'time'                              => ['date_format:H:i'],
            'file'                              => $this->getFileValidationRules(),
            'checkbox_group', 'select_multiple' => ['array'],
            'checkbox'                          => ['boolean'],
            default                             => null,
        };

        if ( null !== $builtInRules ) {
            return $builtInRules;
        }

        // Check for custom type validation rules from the filter hook
        $typeConfig = FieldTypes::getTypeConfig( $this->type );

        if ( null !== $typeConfig && isset( $typeConfig['type_validation'] ) ) {
            $customRules = $typeConfig['type_validation'];

            // Support both array of rules and single rule string
            return is_array( $customRules ) ? $customRules : [$customRules];
        }

        // Default to string validation for unknown types
        return ['string'];
    }

    /**
     * Gets validation rules specific to file uploads.
     *
     * @since 1.0.0
     *
     * @return array<int, string> The file validation rules.
     */
    protected function getFileValidationRules(): array
    {
        $rules = ['file'];

        if ( $types = $this->getConfig( 'allowed_types' ) ) {
            $rules[] = 'mimes:' . implode( ',', $types );
        }

        if ( $maxSize = $this->getConfig( 'max_size' ) ) {
            $rules[] = "max:{$maxSize}";
        }

        return $rules;
    }
}
