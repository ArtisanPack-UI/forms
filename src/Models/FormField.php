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
use ArtisanPackUI\Forms\Contracts\FieldType;
use ArtisanPackUI\Forms\Database\Factories\FormFieldFactory;
use ArtisanPackUI\Forms\Support\Autocomplete;
use ArtisanPackUI\Forms\Support\MergeTags;
use ArtisanPackUI\Forms\Support\PopulationContext;
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
     * Field types whose required state means "must be checked".
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const ACCEPTANCE_TYPES = ['checkbox', 'toggle'];

    /**
     * Field types whose submitted values are option values.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const CHOICE_TYPES = ['select', 'radio', 'checkbox_group', 'select_multiple'];

    /**
     * Rules whose failure means a value is missing, which the custom required
     * message covers rather than the custom error message.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const PRESENCE_RULES = ['required', 'accepted', 'filled', 'present'];

    /**
     * Rules that never fail on their own, so they get no message.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const MESSAGELESS_RULES = ['nullable', 'sometimes', 'bail'];

    /**
     * Heading levels the heading renderer accepts.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const HEADING_LEVELS = ['h2', 'h3', 'h4', 'h5', 'h6'];

    /**
     * Border styles the divider renderer accepts.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const DIVIDER_STYLES = ['solid', 'dashed', 'dotted'];

    /**
     * Spacing sizes the divider renderer accepts.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const DIVIDER_SPACINGS = ['small', 'normal', 'large'];

    /**
     * Accepted time formats: `H:i`, or `H:i:s` when the step is under a minute.
     *
     * A regex rather than `date_format:H:i,H:i:s`: with `date_format` present,
     * `after_or_equal` parses limits with the first format only, so an `H:i:s`
     * value would be compared against a different day than its `H:i` limit.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TIME_FORMAT_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    /**
     * htmLawed configuration for admin-authored html field content.
     *
     * `safe` strips scripts, event handlers, `javascript:` URLs and embeds,
     * which `kses()` does not do with its default configuration. Inline
     * `style` attributes and `<style>` elements are denied as well; the
     * bundled htmLawed also errors on CSS URL handling in `style` attributes.
     *
     * @since 1.6.0
     *
     * @var array<string, int|string>
     */
    public const HTML_SANITIZE_CONFIG = [
        'safe'           => 1,
        'deny_attribute' => 'style',
        'elements'       => '*-style',
    ];

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

    /**
     * The handler resolved for the field's type, kept by fieldType().
     *
     * @since 1.6.0
     */
    protected ?FieldType $resolvedFieldType = null;

    /**
     * Leaves the cached field type handler out when the model is serialized.
     *
     * The handler holds locale-specific labels and may come from a filter, so
     * it is resolved again after unserializing rather than restored.
     *
     * @since 1.6.0
     *
     * @return array<int, string> The property names to serialize.
     */
    public function __sleep(): array
    {
        $this->resolvedFieldType = null;

        return parent::__sleep();
    }

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
     * Reads FieldTypes::WIDTH_CLASSES, falling back to full width.
     *
     * @since 1.0.0
     *
     * @return string The CSS width class.
     */
    public function getWidthClassAttribute(): string
    {
        return FieldTypes::WIDTH_CLASSES[ $this->width ] ?? FieldTypes::WIDTH_CLASSES['full'];
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
     * Gets the maximum upload size in kilobytes for a file field.
     *
     * The builders store this in `field_config`. Fields saved before 1.6.0 kept
     * the builder's value in `validation_rules` (with the type default left in
     * `field_config`), so a legacy `validation_rules` value is the admin's real
     * choice and takes precedence until the field is next saved.
     *
     * @since 1.6.0
     *
     * @return int|null The maximum size in KB, or null when unlimited.
     */
    public function getFileMaxSize(): ?int
    {
        $maxSize = $this->getValidationRule( 'max_size' ) ?? $this->getConfig( 'max_size' );

        if ( ! is_numeric( $maxSize ) || (int) $maxSize <= 0 ) {
            return null;
        }

        return (int) $maxSize;
    }

    /**
     * Gets the allowed file extensions for a file field.
     *
     * Reads `field_config` with the same legacy `validation_rules` precedence as
     * getFileMaxSize(). Accepts an array or a comma-separated string, and
     * normalizes each entry to a lowercase extension without a leading dot.
     * Entries that are not plain alphanumeric extensions are dropped, since
     * the list is interpolated into a `mimes:` validation rule.
     *
     * @since 1.6.0
     *
     * @return list<string> The allowed extensions, or an empty list for any.
     */
    public function getFileAllowedTypes(): array
    {
        $types = $this->getValidationRule( 'allowed_types' ) ?? $this->getConfig( 'allowed_types' );

        if ( is_string( $types ) ) {
            $types = explode( ',', $types );
        }

        if ( ! is_array( $types ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $types as $type ) {
            if ( ! is_string( $type ) ) {
                continue;
            }

            $type = strtolower( ltrim( trim( $type ), '.' ) );

            if ( 1 === preg_match( '/^[a-z0-9]+$/', $type ) ) {
                $normalized[] = $type;
            }
        }

        return array_values( array_unique( $normalized ) );
    }

    /**
     * Gets the label of the option with the given value.
     *
     * Options are stored as a list of `['value' => …, 'label' => …]` entries;
     * a `value => label` map is accepted as well.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The submitted option value.
     *
     * @return string|null The option label, or null when no option matches.
     */
    public function getOptionLabel( string $value ): ?string
    {
        foreach ( $this->options as $key => $option ) {
            if ( is_array( $option ) ) {
                if ( isset( $option['value'] ) && is_scalar( $option['value'] ) && (string) $option['value'] === $value ) {
                    $label = $option['label'] ?? null;

                    return is_scalar( $label ) && '' !== trim( (string) $label ) ? (string) $label : $value;
                }

                continue;
            }

            if ( is_string( $key ) && $key === $value && is_scalar( $option ) ) {
                return (string) $option;
            }
        }

        return null;
    }

    /**
     * Gets the text shown for a checked or unchecked checkbox or toggle.
     *
     * Reads `field_config.checked_label` / `unchecked_label`, defaulting to
     * "Yes" / "No".
     *
     * @since 1.6.0
     *
     * @param  bool  $checked  Whether the box was checked.
     *
     * @return string The display text.
     */
    public function getCheckedDisplayText( bool $checked ): string
    {
        $configured = $this->getConfig( $checked ? 'checked_label' : 'unchecked_label' );

        if ( is_string( $configured ) && '' !== trim( $configured ) ) {
            return $configured;
        }

        return $checked ? __( 'Yes' ) : __( 'No' );
    }

    /**
     * Gets the label shown in submissions, exports and admin notifications.
     *
     * The `admin_label` setting when set, otherwise the label.
     *
     * @since 1.6.0
     *
     * @return string The label.
     */
    public function getAdminLabel(): string
    {
        return $this->getStoredAdminLabel() ?? (string) $this->label;
    }

    /**
     * Gets the `admin_label` setting.
     *
     * Capped at 255 characters to fit the `field_admin_label` column, since
     * the field API can store a longer value than the builder allows.
     *
     * @since 1.6.0
     *
     * @return string|null The admin label, or null when not set.
     */
    public function getStoredAdminLabel(): ?string
    {
        $adminLabel = $this->getConfig( 'admin_label' );

        return is_string( $adminLabel ) && '' !== trim( $adminLabel ) ? mb_substr( trim( $adminLabel ), 0, 255 ) : null;
    }

    /**
     * Gets the `autocomplete` token the field's input exposes.
     *
     * The `autocomplete` setting when it holds a valid token, otherwise the
     * type's default (such as `email` for an email field). Types that do not
     * offer the setting expose none.
     *
     * @since 1.6.0
     *
     * @return string|null The token, or null for none.
     */
    public function getAutocomplete(): ?string
    {
        $definition = $this->fieldType()->definition();

        if ( true !== ( $definition['supports_autocomplete'] ?? false ) ) {
            return null;
        }

        $configured = $this->getConfig( 'autocomplete' );

        if ( Autocomplete::isToken( $configured ) ) {
            return $configured;
        }

        return Autocomplete::isToken( $definition['autocomplete'] ?? null ) ? $definition['autocomplete'] : null;
    }

    /**
     * Gets the custom message shown when any rule but "required" fails.
     *
     * Passed through `__()`, so it may be a translation key.
     *
     * @since 1.6.0
     *
     * @return string|null The message, or null when not set.
     */
    public function getCustomErrorMessage(): ?string
    {
        return $this->translatedConfig( 'error_message' );
    }

    /**
     * Gets the custom message shown when a required field is left empty.
     *
     * Passed through `__()`, so it may be a translation key.
     *
     * @since 1.6.0
     *
     * @return string|null The message, or null when not set.
     */
    public function getCustomRequiredMessage(): ?string
    {
        return $this->translatedConfig( 'required_message' );
    }

    /**
     * Builds the validation messages for this field and any inputs nested under it.
     *
     * The type's own messages, with the custom error message replacing the
     * message of every rule but the presence rules, and the custom required
     * message replacing those. Keyed like FieldType::messages().
     *
     * @since 1.6.0
     *
     * @return array<string, string> The messages.
     */
    public function buildValidationMessages(): array
    {
        if ( $this->isLayoutField() ) {
            return [];
        }

        $messages        = $this->fieldType()->messages( $this );
        $errorMessage    = $this->getCustomErrorMessage();
        $requiredMessage = $this->getCustomRequiredMessage();

        if ( null === $errorMessage && null === $requiredMessage ) {
            return $messages;
        }

        foreach ( $this->buildKeyedValidationRules() as $inputKey => $rules ) {
            $prefix = '' === $inputKey ? '' : "{$inputKey}.";

            foreach ( $rules as $rule ) {
                $name = is_string( $rule ) ? strtolower( trim( explode( ':', $rule, 2 )[0] ) ) : '';

                if ( '' === $name || in_array( $name, self::MESSAGELESS_RULES, true ) ) {
                    continue;
                }

                $custom = in_array( $name, self::PRESENCE_RULES, true ) ? $requiredMessage : $errorMessage;

                if ( null !== $custom ) {
                    $messages[ $prefix . $name ] = $custom;
                }
            }
        }

        return $messages;
    }

    /**
     * Checks whether the field may be filled from the query string, the renderer's prefill or a filter.
     *
     * @since 1.6.0
     *
     * @return bool True when dynamic population is on and the type offers it.
     */
    public function allowsDynamicPopulation(): bool
    {
        return true === filter_var( $this->getConfig( 'dynamic_population', false ), FILTER_VALIDATE_BOOLEAN )
            && $this->offersSetting( 'dynamic_population' );
    }

    /**
     * Gets the parameter name the field is populated by.
     *
     * The `dynamic_parameter` setting, or the field name when it is blank.
     *
     * @since 1.6.0
     *
     * @return string|null The parameter, or null when dynamic population is off.
     */
    public function getPopulationParameter(): ?string
    {
        if ( ! $this->allowsDynamicPopulation() ) {
            return null;
        }

        $parameter = $this->getConfig( 'dynamic_parameter' );

        return is_string( $parameter ) && '' !== trim( $parameter ) ? trim( $parameter ) : (string) $this->name;
    }

    /**
     * Gets the default value with merge tags replaced.
     *
     * @since 1.6.0
     *
     * @param  PopulationContext|null  $context  The visitor and page, or null for the current visitor with page tags blanked.
     *
     * @return string|null The default value, or null when none is set.
     */
    public function getResolvedDefaultValue( ?PopulationContext $context = null ): ?string
    {
        if ( null === $this->default_value ) {
            return null;
        }

        // Most defaults have no merge tags, so skip building a context for them.
        if ( ! str_contains( (string) $this->default_value, '{' ) ) {
            return (string) $this->default_value;
        }

        return MergeTags::resolve( (string) $this->default_value, $context ?? PopulationContext::trusted() );
    }

    /**
     * Normalizes a submitted time value.
     *
     * Browsers submit `H:i:s` when the field's step is under 60 seconds. A
     * zero seconds part carries no information, so it is dropped and the
     * value is stored as `H:i`.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The submitted time.
     *
     * @return string The normalized time.
     */
    public static function normalizeTimeValue( string $value ): string
    {
        if ( 1 === preg_match( '/^(\d{2}:\d{2}):00$/', $value, $matches ) ) {
            return $matches[1];
        }

        return $value;
    }

    /**
     * Gets the display content for a heading, paragraph, or HTML layout field.
     *
     * Storage contract: `heading` → `label`, `paragraph` and `html` →
     * `field_config.content`. The React and Vue builders wrote all three to
     * `default_value` before 1.6.0 and clear it on the next save, so a non-empty
     * `default_value` is unmigrated content and wins. Paragraphs built in the
     * Livewire builder before 1.6.0 fall back to `help_text`, then `label`.
     *
     * @since 1.6.0
     *
     * @return string The raw (unescaped) content.
     */
    public function getLayoutContent(): string
    {
        $legacy = is_string( $this->default_value ) && '' !== trim( $this->default_value )
            ? $this->default_value
            : null;

        $content = $this->getConfig( 'content' );
        $content = is_string( $content ) && '' !== trim( $content ) ? $content : null;

        return (string) match ( $this->type ) {
            'heading'   => $legacy ?? $this->label,
            'paragraph' => $legacy ?? $content ?? ( '' !== trim( (string) $this->help_text ) ? $this->help_text : $this->label ),
            'html'      => $legacy ?? $content ?? '',
            default     => '',
        };
    }

    /**
     * Sanitizes admin-authored HTML for an html layout field.
     *
     * Falls back to escaping the markup when artisanpack-ui/security is not
     * loaded, so the content is shown as text rather than rendered.
     *
     * @since 1.6.0
     *
     * @param  string  $html  The raw HTML.
     *
     * @return string Safe HTML.
     */
    public static function sanitizeHtml( string $html ): string
    {
        if ( ! function_exists( 'security' ) ) {
            return e( $html );
        }

        return security()->kses( $html, self::HTML_SANITIZE_CONFIG );
    }

    /**
     * Builds the Laravel validation rules array for this field.
     *
     * Asks the field type's handler for the rules of the field's own value.
     * Applies the 'ap.forms.validationRules' filter hook to allow
     * third-party packages to modify validation rules for a field.
     *
     * @since 1.0.0
     *
     * @return array<int, mixed> The validation rules array.
     */
    public function buildValidationRules(): array
    {
        // Layout fields don't need validation
        if ( $this->isLayoutField() ) {
            return [];
        }

        return $this->filterValidationRules( $this->fieldType()->rules( $this )[''] ?? [] );
    }

    /**
     * Builds the validation rules for this field and any inputs nested under it.
     *
     * Keyed like FieldType::rules(): `''` for the field's own value (after the
     * `ap.forms.validationRules` filter), a sub-key for a nested input.
     *
     * @since 1.6.0
     *
     * @return array<string, array<int, mixed>> The rules by input key.
     */
    public function buildKeyedValidationRules(): array
    {
        if ( $this->isLayoutField() ) {
            return [];
        }

        $rules     = $this->fieldType()->rules( $this );
        $rules[''] = $this->filterValidationRules( $rules[''] ?? [] );

        return array_filter( $rules, fn ( array $inputRules ): bool => [] !== $inputRules );
    }

    /**
     * Gets the handler for this field's type.
     *
     * The handler is kept on the model for as long as its type stays the same,
     * since views, validation and exports ask for it many times per field.
     *
     * @since 1.6.0
     *
     * @return FieldType The handler.
     */
    public function fieldType(): FieldType
    {
        if ( null === $this->resolvedFieldType || $this->resolvedFieldType->key() !== $this->type ) {
            $this->resolvedFieldType = FieldTypes::handler( (string) $this->type );
        }

        return $this->resolvedFieldType;
    }

    /**
     * Checks whether the field is shown but not editable.
     *
     * Only types that offer the `read_only` setting can be read-only.
     *
     * @since 1.6.0
     *
     * @return bool True when read-only.
     */
    public function isReadOnly(): bool
    {
        return true === filter_var( $this->getConfig( 'read_only', false ), FILTER_VALIDATE_BOOLEAN )
            && $this->offersSetting( 'read_only' );
    }

    /**
     * Gets the value a read-only field is submitted with, whatever the client sent.
     *
     * The field's default value, in the shape its type accepts (see
     * FieldType::readOnlyValue()), passed through the 'ap.forms.readOnlyValue'
     * filter so a package that populates fields dynamically can supply it.
     *
     * @since 1.6.0
     *
     * @return mixed The value.
     */
    public function getReadOnlyValue(): mixed
    {
        return applyFilters( 'ap.forms.readOnlyValue', $this->fieldType()->readOnlyValue( $this ), $this );
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
     * Checks whether the field's type offers a setting.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The setting key.
     *
     * @return bool True when the type's settings schema has the setting.
     */
    protected function offersSetting( string $key ): bool
    {
        foreach ( $this->fieldType()->definition()['settings'] ?? [] as $setting ) {
            if ( $key === ( $setting['key'] ?? null ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a text setting passed through `__()`, or null when blank.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The `field_config` key.
     *
     * @return string|null The translated text.
     */
    protected function translatedConfig( string $key ): ?string
    {
        $value = $this->getConfig( $key );

        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return null;
        }

        $translated = __( trim( $value ) );

        return is_string( $translated ) ? $translated : trim( $value );
    }

    /**
     * Passes the rules for the field's own value through the 'ap.forms.validationRules' filter.
     *
     * @since 1.6.0
     *
     * @param  array<int, mixed>  $rules  The rules.
     *
     * @return array<int, mixed> The filtered rules.
     */
    protected function filterValidationRules( array $rules ): array
    {
        return applyFilters( 'ap.forms.validationRules', $rules, $this );
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
}
