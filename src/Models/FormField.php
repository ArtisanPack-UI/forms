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
use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;
use ArtisanPackUI\Forms\Support\Autocomplete;
use ArtisanPackUI\Forms\Support\ChoiceSources;
use ArtisanPackUI\Forms\Support\DateConstraints;
use ArtisanPackUI\Forms\Support\DateTimeFormat;
use ArtisanPackUI\Forms\Support\EmailDomains;
use ArtisanPackUI\Forms\Support\InputMask;
use ArtisanPackUI\Forms\Support\MergeTags;
use ArtisanPackUI\Forms\Support\NumberFormat;
use ArtisanPackUI\Forms\Support\PopulationContext;
use ArtisanPackUI\Forms\Support\RichText;
use ArtisanPackUI\Forms\Support\UrlSchemes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

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
    public const ACCEPTANCE_TYPES = ['checkbox', 'toggle', 'consent'];

    /**
     * Field types whose submitted values are option values.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const CHOICE_TYPES = ['select', 'radio', 'checkbox_group', 'select_multiple'];

    /**
     * Choice field types whose submitted value is a list of option values.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const MULTIPLE_CHOICE_TYPES = ['checkbox_group', 'select_multiple'];

    /**
     * Layouts a radio or checkbox group may arrange its choices in.
     *
     * Column layouts fill row by row, so the reading order matches the DOM order.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const CHOICE_LAYOUTS = ['stacked', 'inline', '2-col', '3-col'];

    /**
     * Where a composite field shows the labels of its sub-inputs.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const SUB_LABEL_PLACEMENTS = ['below', 'above', 'hidden'];

    /**
     * How a composite field is exported: one column per enabled sub-input, or one formatted column.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const COMPOSITE_EXPORT_FORMATS = ['columns', 'single'];

    /**
     * Input types a composite field's sub-input may use.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const SUB_INPUT_TYPES = ['text', 'email', 'tel', 'url', 'number', 'select'];

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
     * Label placements a field or form may set.
     *
     * `left` sits beside the input from the `md` breakpoint up; `hidden`
     * keeps the label for assistive technology (`sr-only`).
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const LABEL_PLACEMENTS = ['top', 'left', 'hidden'];

    /**
     * Description (help text) placements a field or form may set.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const DESCRIPTION_PLACEMENTS = ['above', 'below'];

    /**
     * Field visibilities: `administrative` fields are left out of the public
     * form and edited by admins on the submission.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const VISIBILITIES = ['visible', 'administrative'];

    /**
     * The value a renderer submits for a selected "Other" choice whose text is
     * blank. Renderers replace it with the typed text, so a submitted copy
     * means the text is missing.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const OTHER_CHOICE_VALUE = '__other__';

    /**
     * Formats a phone field may validate numbers against: `any` skips the
     * check, `us` takes US numbers and `international` any country's, with
     * a country selector.
     *
     * @since 1.6.0
     *
     * @var list<string>
     */
    public const PHONE_FORMATS = ['any', 'us', 'international'];

    /**
     * The longest "Other" text a submission may hold.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const OTHER_CHOICE_MAX_LENGTH = 255;

    /**
     * The longest "redact after N days" period a field may set.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_REDACT_DAYS = 3650;

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
     * The form read for form-level settings, kept by settingsForm().
     *
     * @since 1.6.0
     */
    protected ?Form $settingsForm = null;

    /**
     * The seed randomized choices are shuffled with, kept by withChoiceSeed().
     *
     * @since 1.6.0
     */
    protected ?int $choiceSeed = null;

    /**
     * Leaves the cached field type handler and settings form out when the model is serialized.
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
        $this->settingsForm      = null;

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
     * Since 1.6.0, a field whose `choices_source` setting names a registered
     * source gets that source's choices (see ChoiceSources) instead of the
     * options stored in `field_config`.
     *
     * @since 1.0.0
     *
     * @return array<int|string, mixed> The field options array.
     */
    public function getOptionsAttribute(): array
    {
        $source = $this->getChoicesSource();

        if ( null !== $source ) {
            return app( ChoiceSources::class )->resolve( $source, $this );
        }

        $options = $this->field_config['options'] ?? [];

        return is_array( $options ) ? $options : [];
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
     * Checks whether a file field accepts more than one file.
     *
     * @since 1.6.0
     *
     * @return bool True when the `multiple` setting is on and the type offers it.
     */
    public function allowsMultipleFiles(): bool
    {
        return $this->configFlag( 'multiple' ) && $this->offersSetting( 'multiple' );
    }

    /**
     * Gets the most files a multi-file field accepts.
     *
     * @since 1.6.0
     *
     * @return int|null The count, or null when unlimited or the field takes one file.
     */
    public function getFileMaxCount(): ?int
    {
        return $this->allowsMultipleFiles() ? $this->positiveConfigInteger( 'max_files' ) : null;
    }

    /**
     * Gets the largest total size in kilobytes a multi-file field accepts.
     *
     * @since 1.6.0
     *
     * @return int|null The size, or null when unlimited or the field takes one file.
     */
    public function getFileMaxTotalSize(): ?int
    {
        return $this->allowsMultipleFiles() ? $this->positiveConfigInteger( 'max_total_size' ) : null;
    }

    /**
     * Gets the pixel bounds uploaded images must fit.
     *
     * @since 1.6.0
     *
     * @return array{min_width: int|null, max_width: int|null, min_height: int|null, max_height: int|null} The bounds; null when unset.
     */
    public function getImageDimensionLimits(): array
    {
        $limits = [];

        foreach ( ['min_width', 'max_width', 'min_height', 'max_height'] as $key ) {
            $limits[ $key ] = $this->offersSetting( $key ) ? $this->positiveConfigInteger( $key ) : null;
        }

        return $limits;
    }

    /**
     * Gets the input mask of a text or phone field.
     *
     * @since 1.6.0
     *
     * @return string|null The mask (see InputMask), or null when none is set.
     */
    public function getInputMask(): ?string
    {
        if ( ! $this->offersSetting( 'input_mask' ) ) {
            return null;
        }

        return InputMask::resolve( $this->getConfig( 'input_mask' ), $this->getConfig( 'custom_mask' ) );
    }

    /**
     * Gets the hint that tells visitors the format the field's mask expects.
     *
     * @since 1.6.0
     *
     * @return string|null The hint, or null when the field has no mask.
     */
    public function getInputMaskHint(): ?string
    {
        $mask = $this->getInputMask();

        return null === $mask ? null : InputMask::hint( $mask );
    }

    /**
     * Checks whether a text or textarea field shows a live character or word counter.
     *
     * @since 1.6.0
     *
     * @return bool True when the `show_counter` setting is on and the type offers it.
     */
    public function showsCounter(): bool
    {
        return $this->configFlag( 'show_counter' ) && $this->offersSetting( 'show_counter' );
    }

    /**
     * Gets a text field's length limit, for the `minlength` and `maxlength` attributes.
     *
     * @since 1.6.0
     *
     * @param  string  $key  `min` or `max`.
     *
     * @return int|null The limit, or null when unset or the type limits a number rather than a length.
     */
    public function getLengthLimit( string $key ): ?int
    {
        if ( ! in_array( $this->type, ['text', 'textarea'], true ) ) {
            return null;
        }

        $limit = $this->getValidationRule( $key );

        return is_numeric( $limit ) && (int) $limit >= 0 ? (int) $limit : null;
    }

    /**
     * Gets a textarea's word limit.
     *
     * @since 1.6.0
     *
     * @return int|null The limit, or null when unset.
     */
    public function getMaxWords(): ?int
    {
        if ( ! $this->offersSetting( 'max_words' ) ) {
            return null;
        }

        $limit = $this->getValidationRule( 'max_words' );

        return is_numeric( $limit ) && (int) $limit > 0 ? (int) $limit : null;
    }

    /**
     * Gets the format a number field reads typed numbers in.
     *
     * @since 1.6.0
     *
     * @return string|null One of NumberFormat::FORMATS, or null for a plain number input.
     */
    public function getNumberFormat(): ?string
    {
        $format = $this->offersSetting( 'number_format' ) ? $this->getConfig( 'number_format' ) : null;

        return is_string( $format ) && in_array( $format, NumberFormat::FORMATS, true ) ? $format : null;
    }

    /**
     * Checks whether a number field accepts whole numbers only.
     *
     * @since 1.6.0
     *
     * @return bool True when the `integer_only` rule is on and the type offers it.
     */
    public function isIntegerOnly(): bool
    {
        return true === filter_var( $this->getValidationRule( 'integer_only', false ), FILTER_VALIDATE_BOOLEAN )
            && $this->offersSetting( 'integer_only' );
    }

    /**
     * Gets the text shown before and after a number field's input.
     *
     * @since 1.6.0
     *
     * @return array{prefix: string|null, suffix: string|null} The prefix and suffix; null when unset.
     */
    public function getAffixes(): array
    {
        $affixes = [];

        foreach ( ['prefix', 'suffix'] as $key ) {
            $value           = $this->offersSetting( $key ) ? $this->getConfig( $key ) : null;
            $affixes[ $key ] = is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;
        }

        return $affixes;
    }

    /**
     * Gets the description that gives a number field's prefix and suffix to assistive technology.
     *
     * The renderers show the affixes beside the input and hide them from
     * screen readers, so this text (referenced by `aria-describedby`) is
     * how they are announced.
     *
     * @since 1.6.0
     *
     * @return string|null The description, or null when the field has neither.
     */
    public function getAffixDescription(): ?string
    {
        ['prefix' => $prefix, 'suffix' => $suffix] = $this->getAffixes();

        return match ( true ) {
            null !== $prefix && null !== $suffix => __( 'Prefix: :prefix. Suffix: :suffix.', ['prefix' => $prefix, 'suffix' => $suffix] ),
            null !== $prefix                     => __( 'Prefix: :prefix.', ['prefix' => $prefix] ),
            null !== $suffix                     => __( 'Suffix: :suffix.', ['suffix' => $suffix] ),
            default                              => null,
        };
    }

    /**
     * Gets the format a phone field validates numbers against.
     *
     * @since 1.6.0
     *
     * @return string `any` (no check), `us` or `international`.
     */
    public function getPhoneFormat(): string
    {
        $format = $this->offersSetting( 'phone_format' ) ? $this->getConfig( 'phone_format' ) : null;

        return is_string( $format ) && in_array( $format, self::PHONE_FORMATS, true ) ? $format : 'any';
    }

    /**
     * Gets the country an international phone field starts with and reads national numbers in.
     *
     * @since 1.6.0
     *
     * @return string The ISO 3166-1 alpha-2 code.
     */
    public function getPhoneDefaultCountry(): string
    {
        $country = $this->getConfig( 'default_country' );

        return is_string( $country ) && 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : 'US';
    }

    /**
     * Checks whether a phone field shows a country selector.
     *
     * @since 1.6.0
     *
     * @return bool True for the `international` format.
     */
    public function usesPhoneCountrySelector(): bool
    {
        return 'international' === $this->getPhoneFormat();
    }

    /**
     * Gets the key the Livewire renderer submits a phone field's selected country under.
     *
     * The `phone` rule reads `{attribute}_country`, and the country is
     * dropped once the number is stored in E.164 form.
     *
     * @since 1.6.0
     *
     * @return string The key, `{name}_country`.
     */
    public function getPhoneCountryKey(): string
    {
        return $this->name . '_country';
    }

    /**
     * Gets how strictly an email field checks addresses.
     *
     * @since 1.6.0
     *
     * @return string `rfc` (default), `dns` or `strict`; see EmailDomains::VALIDATION_RULES.
     */
    public function getEmailValidation(): string
    {
        $mode = $this->offersSetting( 'email_validation' ) ? $this->getValidationRule( 'email_validation' ) : null;

        return is_string( $mode ) && isset( EmailDomains::VALIDATION_RULES[ $mode ] ) ? $mode : 'rfc';
    }

    /**
     * Gets an email field's allowed or blocked domains.
     *
     * @since 1.6.0
     *
     * @param  string  $list  `allowed` or `blocked`.
     *
     * @return list<string> The lowercase domains; empty when the list is unset.
     */
    public function getEmailDomains( string $list ): array
    {
        $key = "{$list}_domains";

        return $this->offersSetting( $key ) ? EmailDomains::normalizeList( $this->getValidationRule( $key ) ) : [];
    }

    /**
     * Gets the schemes a URL field accepts.
     *
     * @since 1.6.0
     *
     * @return list<string> The lowercase schemes; `http` and `https` when unset.
     */
    public function getAllowedUrlSchemes(): array
    {
        return UrlSchemes::normalizeList( $this->offersSetting( 'allowed_schemes' ) ? $this->getValidationRule( 'allowed_schemes' ) : null );
    }

    /**
     * Checks whether a textarea uses the rich text editor.
     *
     * @since 1.6.0
     *
     * @return bool True when the `rich_text` setting is on and the type offers it.
     */
    public function usesRichText(): bool
    {
        return $this->configFlag( 'rich_text' ) && $this->offersSetting( 'rich_text' );
    }

    /**
     * Gets a date field's earliest and latest allowed dates, resolved for today.
     *
     * The min and max date settings (absolute or relative, see
     * DateConstraints), narrowed by the "No past dates" or "No future dates"
     * range. The age limits are checked by their own rules and left out.
     *
     * @since 1.6.0
     *
     * @return array{min: string|null, max: string|null} The `Y-m-d` bounds; null when unbounded.
     */
    public function getDateLimits(): array
    {
        if ( ! $this->offersSetting( 'min_date' ) ) {
            return ['min' => null, 'max' => null];
        }

        $range = $this->offersSetting( 'date_range' ) ? $this->getValidationRule( 'date_range' ) : null;
        $today = in_array( $range, DateConstraints::RANGES, true ) ? DateConstraints::resolve( 'today' ) : null;

        return DateConstraints::combine(
            [DateConstraints::resolve( $this->getValidationRule( 'min_date' ) ), 'no_past' === $range ? $today : null],
            [DateConstraints::resolve( $this->getValidationRule( 'max_date' ) ), 'no_future' === $range ? $today : null],
        );
    }

    /**
     * Gets the bounds a date field's input offers: its limits narrowed by its age limits.
     *
     * Used for the native `min` and `max` attributes and the year dropdown.
     *
     * @since 1.6.0
     *
     * @return array{min: string|null, max: string|null} The `Y-m-d` bounds; null when unbounded.
     */
    public function getDateBounds(): array
    {
        $limits = $this->getDateLimits();
        $minAge = $this->getAgeLimit( 'min_age' );
        $maxAge = $this->getAgeLimit( 'max_age' );

        return DateConstraints::combine(
            [$limits['min'], null === $maxAge ? null : DateConstraints::earliestBirthdate( $maxAge )],
            [$limits['max'], null === $minAge ? null : DateConstraints::latestBirthdate( $minAge )],
        );
    }

    /**
     * Gets a date field's minimum or maximum age, for a birthdate.
     *
     * @since 1.6.0
     *
     * @param  string  $key  `min_age` or `max_age`.
     *
     * @return int|null The age in years, or null when unset.
     */
    public function getAgeLimit( string $key ): ?int
    {
        if ( ! $this->offersSetting( $key ) ) {
            return null;
        }

        $age = $this->getValidationRule( $key );

        return is_numeric( $age ) && (int) $age >= 0 ? (int) $age : null;
    }

    /**
     * Checks whether a date field rejects Saturdays and Sundays.
     *
     * @since 1.6.0
     *
     * @return bool True when the `disable_weekends` rule is on and the type offers it.
     */
    public function disablesWeekends(): bool
    {
        return true === filter_var( $this->getValidationRule( 'disable_weekends', false ), FILTER_VALIDATE_BOOLEAN )
            && $this->offersSetting( 'disable_weekends' );
    }

    /**
     * Gets the specific dates a date field rejects.
     *
     * @since 1.6.0
     *
     * @return list<string> The sorted `Y-m-d` dates.
     */
    public function getDisabledDates(): array
    {
        return $this->offersSetting( 'disabled_dates' ) ? DateConstraints::normalizeDates( $this->getValidationRule( 'disabled_dates' ) ) : [];
    }

    /**
     * Gets how a date field shows dates and orders its month, day and year inputs.
     *
     * @since 1.6.0
     *
     * @return string `ymd` (default), `mdy` or `dmy`.
     */
    public function getDateFormat(): string
    {
        $format = $this->offersSetting( 'date_format' ) ? $this->getConfig( 'date_format' ) : null;

        return is_string( $format ) && isset( DateTimeFormat::DATE_FORMATS[ $format ] ) ? $format : 'ymd';
    }

    /**
     * Gets how a date field is filled in.
     *
     * @since 1.6.0
     *
     * @return string `picker` (default), `dropdowns` or `fields`.
     */
    public function getDateInputMode(): string
    {
        $mode = $this->offersSetting( 'date_input' ) ? $this->getConfig( 'date_input' ) : null;

        return is_string( $mode ) && in_array( $mode, DateTimeFormat::DATE_INPUTS, true ) ? $mode : 'picker';
    }

    /**
     * Gets whether a time field shows a 24-hour or 12-hour clock.
     *
     * @since 1.6.0
     *
     * @return string `24` (default) or `12`.
     */
    public function getTimeFormat(): string
    {
        $format = $this->offersSetting( 'time_format' ) ? $this->getConfig( 'time_format' ) : null;

        return is_scalar( $format ) && in_array( (string) $format, DateTimeFormat::TIME_FORMATS, true ) ? (string) $format : '24';
    }

    /**
     * Gets how a time field is filled in.
     *
     * @since 1.6.0
     *
     * @return string `picker` (default) or `dropdowns`.
     */
    public function getTimeInputMode(): string
    {
        $mode = $this->offersSetting( 'time_input' ) ? $this->getConfig( 'time_input' ) : null;

        return is_string( $mode ) && in_array( $mode, DateTimeFormat::TIME_INPUTS, true ) ? $mode : 'picker';
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
     * Gets the registered source the field loads its choices from.
     *
     * @since 1.6.0
     *
     * @return string|null The `choices_source` setting when the type offers it and the source is registered, otherwise null.
     */
    public function getChoicesSource(): ?string
    {
        $source = $this->getConfig( 'choices_source' );

        if ( ! is_string( $source ) || '' === $source || ! $this->offersSetting( 'choices_source' ) ) {
            return null;
        }

        return app( ChoiceSources::class )->has( $source ) ? $source : null;
    }

    /**
     * Checks whether the field's value is a list of option values.
     *
     * @since 1.6.0
     *
     * @return bool True for a checkbox group or multi-select.
     */
    public function isMultipleChoice(): bool
    {
        return in_array( $this->type, self::MULTIPLE_CHOICE_TYPES, true );
    }

    /**
     * Gets the values of the options marked as selected by default.
     *
     * A single-choice field takes the first marked option only.
     *
     * @since 1.6.0
     *
     * @return list<string> The option values.
     */
    public function getDefaultOptionValues(): array
    {
        if ( ! in_array( $this->type, self::CHOICE_TYPES, true ) ) {
            return [];
        }

        $values = [];

        foreach ( $this->options as $option ) {
            if ( ! is_array( $option ) || ! isset( $option['value'] ) || ! is_scalar( $option['value'] ) ) {
                continue;
            }

            if ( true === filter_var( $option['default'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
                $values[] = (string) $option['value'];
            }
        }

        $values = array_values( array_unique( $values ) );

        return $this->isMultipleChoice() ? $values : array_slice( $values, 0, 1 );
    }

    /**
     * Gets how a radio or checkbox group arranges its choices.
     *
     * @since 1.6.0
     *
     * @return string `stacked` (the default), `inline`, `2-col` or `3-col`.
     */
    public function getChoiceLayout(): string
    {
        $layout = $this->getConfig( 'layout' );

        return is_string( $layout ) && in_array( $layout, self::CHOICE_LAYOUTS, true ) && $this->offersSetting( 'layout' )
            ? $layout
            : 'stacked';
    }

    /**
     * Gets the CSS classes that arrange a radio or checkbox group's choices.
     *
     * Columns stack below the `sm` breakpoint. The React and Vue renderers
     * mirror these (`resources/js/shared/choices.ts`).
     *
     * @since 1.6.0
     *
     * @return string The classes.
     */
    public function getChoiceLayoutClasses(): string
    {
        return match ( $this->getChoiceLayout() ) {
            'inline' => 'flex flex-wrap gap-x-6 gap-y-2',
            '2-col'  => 'grid grid-cols-1 gap-2 sm:grid-cols-2',
            '3-col'  => 'grid grid-cols-1 gap-2 sm:grid-cols-3',
            default  => 'flex flex-col gap-2',
        };
    }

    /**
     * Checks whether the field shows its choices in a random order.
     *
     * @since 1.6.0
     *
     * @return bool True when the `randomize` setting is on and the type offers it.
     */
    public function randomizesChoices(): bool
    {
        return $this->configFlag( 'randomize' ) && $this->offersSetting( 'randomize' );
    }

    /**
     * Checks whether a checkbox group offers a "Select All" choice.
     *
     * @since 1.6.0
     *
     * @return bool True when the `select_all` setting is on and the type offers it.
     */
    public function offersSelectAll(): bool
    {
        return $this->configFlag( 'select_all' ) && $this->offersSetting( 'select_all' );
    }

    /**
     * Checks whether a select or multi-select is rendered as a searchable combobox.
     *
     * @since 1.6.0
     *
     * @return bool True when the `searchable` setting is on and the type offers it.
     */
    public function isSearchable(): bool
    {
        return $this->configFlag( 'searchable' ) && $this->offersSetting( 'searchable' );
    }

    /**
     * Sets the seed randomized choices are shuffled with.
     *
     * The Livewire renderer passes one seed per visitor, so its re-renders
     * keep the same order. Without a seed the order changes per instance.
     *
     * @since 1.6.0
     *
     * @param  int  $seed  The seed.
     *
     * @return static The field.
     */
    public function withChoiceSeed( int $seed ): static
    {
        $this->choiceSeed = $seed;

        return $this;
    }

    /**
     * Gets the options in the order the renderer shows them.
     *
     * Shuffled when the field randomizes its choices, deterministically for
     * the seed (see withChoiceSeed()) mixed with the field's ID, so two
     * randomized fields on a form get different orders.
     *
     * @since 1.6.0
     *
     * @return array<int|string, mixed> The options.
     */
    public function getRenderOptions(): array
    {
        $options = $this->options;

        if ( ! $this->randomizesChoices() || count( $options ) < 2 || ! array_is_list( $options ) ) {
            return $options;
        }

        $this->choiceSeed ??= random_int( 0, PHP_INT_MAX );
        $engine             = new Mt19937( (int) crc32( $this->choiceSeed . ':' . ( $this->getKey() ?? $this->name ) ) );

        return ( new Randomizer( $engine ) )->shuffleArray( $options );
    }

    /**
     * Checks whether the field collects several inputs stored together.
     *
     * @since 1.6.0
     *
     * @return bool True when the field's type handler extends CompositeFieldType and declares sub-inputs.
     */
    public function isComposite(): bool
    {
        return [] !== $this->getSubInputs();
    }

    /**
     * Gets a composite field's sub-inputs, with the field's own settings applied.
     *
     * Only types whose handler extends CompositeFieldType have sub-inputs. The
     * handler's sub-input definitions (see
     * CompositeFieldType::subInputDefinitions()) give each sub-input's key,
     * default label and placeholder, whether it is enabled and required by
     * default, its `autocomplete` token, input `type`, whether it is `wide`
     * (spans the whole row), a select's `options`, its `default` value and
     * population `parameter`, and extra validation `rules`. The field's `field_config.sub_inputs.{key}` may change
     * `enabled`, `label`, `placeholder`, `required`, `default` and `parameter`.
     *
     * @since 1.6.0
     *
     * @return array<string, array{key: string, label: string, default_label: string, placeholder: string|null, enabled: bool, required: bool, autocomplete: string|null, type: string, wide: bool, options: list<array{value: string, label: string}>, default: string|null, parameter: string|null, rules: list<mixed>}> The sub-inputs by key, in definition order.
     */
    public function getSubInputs(): array
    {
        $handler = $this->fieldType();

        if ( ! $handler instanceof CompositeFieldType ) {
            return [];
        }

        $definitions = $handler->subInputDefinitions( $this );
        $configured  = $this->getConfig( 'sub_inputs', [] );
        $subInputs   = [];

        foreach ( $definitions as $definition ) {
            $key = is_array( $definition ) ? ( $definition['key'] ?? null ) : null;

            if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $key ) || isset( $subInputs[ $key ] ) ) {
                continue;
            }

            $config       = is_array( $configured ) && is_array( $configured[ $key ] ?? null ) ? $configured[ $key ] : [];
            $defaultLabel = is_scalar( $definition['label'] ?? null ) ? (string) $definition['label'] : Str::headline( $key );
            $label        = $config['label'] ?? null;
            $placeholder  = $config['placeholder'] ?? $definition['placeholder'] ?? null;
            $type         = $definition['type'] ?? 'text';
            $type         = in_array( $type, self::SUB_INPUT_TYPES, true ) ? $type : 'text';
            $rules        = $definition['rules'] ?? [];
            $options      = 'select' === $type ? self::normalizeSubInputOptions( $definition['options'] ?? [] ) : [];
            $default      = $this->subInputText( $config, $definition, 'default' );
            $parameter    = $this->subInputText( $config, $definition, 'parameter' );

            $subInputs[ $key ] = [
                'key'           => $key,
                'label'         => is_string( $label ) && '' !== trim( $label ) ? trim( $label ) : $defaultLabel,
                'default_label' => $defaultLabel,
                'placeholder'   => is_string( $placeholder ) && '' !== trim( $placeholder ) ? trim( $placeholder ) : null,
                'enabled'       => $this->subInputFlag( $config, $definition, 'enabled', true ),
                'required'      => $this->subInputFlag( $config, $definition, 'required', false ),
                'autocomplete'  => Autocomplete::isToken( $definition['autocomplete'] ?? null ) ? $definition['autocomplete'] : null,
                'type'          => $type,
                'wide'          => true === ( $definition['wide'] ?? false ),
                'options'       => $options,
                'default'       => $default,
                'parameter'     => null === $parameter ? null : ( (string) preg_replace( CompositeFieldType::PARAMETER_PATTERN, '', $parameter ) ?: null ),
                'rules'         => is_array( $rules ) ? array_values( $rules ) : [$rules],
            ];
        }

        return $subInputs;
    }

    /**
     * Gets a composite field's enabled sub-inputs.
     *
     * @since 1.6.0
     *
     * @return array<string, array<string, mixed>> The sub-inputs by key (see getSubInputs()).
     */
    public function getEnabledSubInputs(): array
    {
        return array_filter( $this->getSubInputs(), fn ( array $subInput ): bool => $subInput['enabled'] );
    }

    /**
     * Gets the keys of a composite field's sub-inputs that must be filled in.
     *
     * Only a required field requires its sub-inputs: those marked required,
     * or every enabled sub-input when none is marked.
     *
     * @since 1.6.0
     *
     * @return list<string> The sub-input keys.
     */
    public function getRequiredSubInputKeys(): array
    {
        if ( ! $this->is_required ) {
            return [];
        }

        $enabled  = $this->getEnabledSubInputs();
        $required = array_keys( array_filter( $enabled, fn ( array $subInput ): bool => $subInput['required'] ) );

        return [] === $required ? array_keys( $enabled ) : $required;
    }

    /**
     * Checks whether an email field asks for the address twice.
     *
     * A read-only email field never does, since the visitor cannot type it,
     * nor does an administrative one, which admins fill in on the submission.
     *
     * @since 1.6.0
     *
     * @return bool True when `confirm_email` is on for an editable, public email field.
     */
    public function requiresEmailConfirmation(): bool
    {
        return 'email' === $this->type && $this->configFlag( 'confirm_email' ) && ! $this->isReadOnly() && ! $this->isAdministrative();
    }

    /**
     * Gets the data key an email field's confirmation is submitted under.
     *
     * Laravel's `confirmed` rule reads it from beside the field's own value.
     *
     * @since 1.6.0
     *
     * @return string `{name}_confirmation`.
     */
    public function getConfirmationKey(): string
    {
        return $this->name . '_confirmation';
    }

    /**
     * Gets the sub-labels of an email field's two inputs when confirmation is on.
     *
     * @since 1.6.0
     *
     * @return array{email: string, confirm: string, confirm_placeholder: string|null} The sub-labels and the
     *                                                                                 confirm input's placeholder.
     */
    public function getEmailConfirmationLabels(): array
    {
        return [
            'email'               => $this->translatedConfig( 'email_sub_label' ) ?? __( 'Enter Email' ),
            'confirm'             => $this->translatedConfig( 'confirm_sub_label' ) ?? __( 'Confirm Email' ),
            'confirm_placeholder' => $this->translatedConfig( 'confirm_placeholder' ) ?? $this->placeholder,
        ];
    }

    /**
     * Gets where a composite field shows its sub-input labels.
     *
     * @since 1.6.0
     *
     * @return string `below` (the default), `above` or `hidden` (`sr-only`, still announced).
     */
    public function getSubLabelPlacement(): string
    {
        $placement = $this->getConfig( 'sub_label_placement' );

        return is_string( $placement ) && in_array( $placement, self::SUB_LABEL_PLACEMENTS, true ) ? $placement : 'below';
    }

    /**
     * Gets how a composite field is exported to CSV.
     *
     * @since 1.6.0
     *
     * @return string `columns` (the default; one per enabled sub-input) or `single` (one formatted column).
     */
    public function getCompositeExportFormat(): string
    {
        $format = $this->getConfig( 'export_format' );

        return is_string( $format ) && in_array( $format, self::COMPOSITE_EXPORT_FORMATS, true ) ? $format : 'columns';
    }

    /**
     * Gets the targets a conditional logic rule may test on this field.
     *
     * The field itself, or for a composite field each enabled sub-input,
     * referenced as `{name}.{key}` (or `{uuid}.{key}`).
     *
     * @since 1.6.0
     *
     * @return list<array{name: string, label: string, type: string, uuid: string, options: array<int|string, mixed>}> The targets.
     */
    public function getConditionTargets(): array
    {
        if ( ! $this->isComposite() ) {
            return [[
                'name'    => (string) $this->name,
                'label'   => (string) $this->label,
                'type'    => (string) $this->type,
                'uuid'    => (string) $this->uuid,
                'options' => $this->getConditionOptions(),
            ]];
        }

        $targets = [];

        foreach ( $this->getEnabledSubInputs() as $key => $subInput ) {
            $targets[] = [
                'name'    => "{$this->name}.{$key}",
                'label'   => __( ':field: :part', ['field' => $this->label, 'part' => $subInput['label']] ),
                'type'    => 'text',
                'uuid'    => "{$this->uuid}.{$key}",
                'options' => [],
            ];
        }

        return $targets;
    }

    /**
     * Gets the label a choice field shows for a stored value.
     *
     * The option's label, or "{Other label}: {value}" for text typed into the
     * "Other" choice.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The stored value.
     *
     * @return string|null The label, or null when no option matches and the field has no "Other" choice.
     */
    public function getChoiceDisplayLabel( string $value ): ?string
    {
        $label = $this->getOptionLabel( $value );

        if ( null !== $label || ! $this->isOtherChoiceValue( $value ) ) {
            return $label;
        }

        return __( ':label: :value', ['label' => $this->getOtherChoiceLabel(), 'value' => $value] );
    }

    /**
     * Gets the options a conditional logic rule on this field can test for.
     *
     * The field's options, followed by its "Other" choice when it has one, so
     * a rule can match any text typed into it.
     *
     * @since 1.6.0
     *
     * @return array<int|string, mixed> The options.
     */
    public function getConditionOptions(): array
    {
        $options = $this->options;

        if ( $this->allowsOtherChoice() && array_is_list( $options ) ) {
            $options[] = ['value' => self::OTHER_CHOICE_VALUE, 'label' => $this->getOtherChoiceLabel()];
        }

        return $options;
    }

    /**
     * Checks whether the field offers an "Other" choice with a text input.
     *
     * @since 1.6.0
     *
     * @return bool True when the `enable_other` setting is on and the type offers it.
     */
    public function allowsOtherChoice(): bool
    {
        return $this->configFlag( 'enable_other' ) && $this->offersSetting( 'enable_other' );
    }

    /**
     * Gets the label of the "Other" choice.
     *
     * @since 1.6.0
     *
     * @return string The `other_label` setting, or "Other".
     */
    public function getOtherChoiceLabel(): string
    {
        return $this->translatedConfig( 'other_label' ) ?? __( 'Other' );
    }

    /**
     * Checks whether a submitted or stored value is text typed into the "Other" choice.
     *
     * Any non-blank value that matches no option counts, as does the
     * placeholder a renderer submits when the text is blank.
     *
     * @since 1.6.0
     *
     * @param  string  $value  The value.
     *
     * @return bool True for an "Other" value.
     */
    public function isOtherChoiceValue( string $value ): bool
    {
        if ( ! $this->allowsOtherChoice() || '' === trim( $value ) ) {
            return false;
        }

        return self::OTHER_CHOICE_VALUE === $value || null === $this->getOptionLabel( $value );
    }

    /**
     * Gets the label placement set on the field or, failing that, its form.
     *
     * @since 1.6.0
     *
     * @return string|null `top`, `left` or `hidden`, or null when neither sets one.
     */
    public function getConfiguredLabelPlacement(): ?string
    {
        return $this->placementSetting( 'label_placement', self::LABEL_PLACEMENTS );
    }

    /**
     * Gets where the label is shown.
     *
     * @since 1.6.0
     *
     * @return string `top` (the default), `left` or `hidden`.
     */
    public function getLabelPlacement(): string
    {
        return $this->getConfiguredLabelPlacement() ?? 'top';
    }

    /**
     * Gets the description placement set on the field or, failing that, its form.
     *
     * @since 1.6.0
     *
     * @return string|null `above` or `below`, or null when neither sets one.
     */
    public function getConfiguredDescriptionPlacement(): ?string
    {
        return $this->placementSetting( 'description_placement', self::DESCRIPTION_PLACEMENTS );
    }

    /**
     * Gets where the description (help text) is shown relative to the input.
     *
     * @since 1.6.0
     *
     * @return string `below` (the default) or `above`.
     */
    public function getDescriptionPlacement(): string
    {
        return $this->getConfiguredDescriptionPlacement() ?? 'below';
    }

    /**
     * Gets the `aria-describedby` value for the field's input.
     *
     * References the `error-{name}` and `hint-{name}` elements the Blade
     * partials render, wherever the description is placed.
     *
     * @since 1.6.0
     *
     * @param  string|null  $error  The validation error, or null.
     *
     * @return string|null The space-separated IDs, or null for none.
     */
    public function getDescribedBy( ?string $error = null ): ?string
    {
        $ids = array_filter( [
            filled( $error ) ? 'error-' . $this->name : null,
            filled( $this->help_text ) ? 'hint-' . $this->name : null,
            null !== $this->getInputMask() ? 'mask-' . $this->name : null,
            $this->showsCounter() ? 'counter-' . $this->name : null,
            null !== $this->getAffixDescription() ? 'affix-' . $this->name : null,
        ] );

        return [] === $ids ? null : implode( ' ', $ids );
    }

    /**
     * Checks whether the field is left out of the public form and edited by admins.
     *
     * @since 1.6.0
     *
     * @return bool True when the `visibility` setting is `administrative` and the type offers it.
     */
    public function isAdministrative(): bool
    {
        return 'administrative' === $this->getConfig( 'visibility' ) && $this->offersSetting( 'visibility' );
    }

    /**
     * Checks whether a value must not repeat one already submitted for the field.
     *
     * @since 1.6.0
     *
     * @return bool True when the `no_duplicates` setting is on and the type offers it.
     */
    public function rejectsDuplicates(): bool
    {
        return $this->configFlag( 'no_duplicates' ) && $this->offersSetting( 'no_duplicates' );
    }

    /**
     * Checks whether submitted values are saved.
     *
     * A field with "Do not store" on still reaches notifications and webhooks
     * for the request it was submitted in, but is never written to the database.
     *
     * @since 1.6.0
     *
     * @return bool False when the `do_not_store` setting is on and the type offers it.
     */
    public function storesValues(): bool
    {
        return ! ( $this->configFlag( 'do_not_store' ) && $this->offersSetting( 'do_not_store' ) );
    }

    /**
     * Checks whether stored values are masked in the admin unless the viewer may reveal them.
     *
     * @since 1.6.0
     *
     * @return bool True when the `mask_in_admin` setting is on and the type offers it.
     */
    public function isMaskedInAdmin(): bool
    {
        return $this->configFlag( 'mask_in_admin' ) && $this->offersSetting( 'mask_in_admin' );
    }

    /**
     * Gets how many days stored values are kept before `forms:prune-submissions` redacts them.
     *
     * @since 1.6.0
     *
     * @return int|null The days, or null to keep values until the submission is pruned.
     */
    public function getRedactAfterDays(): ?int
    {
        if ( ! $this->offersSetting( 'redact_after_days' ) ) {
            return null;
        }

        $days = filter_var( $this->getConfig( 'redact_after_days' ), FILTER_VALIDATE_INT );

        return false !== $days && $days >= 1 && $days <= self::MAX_REDACT_DAYS ? $days : null;
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
        $value = applyFilters( 'ap.forms.readOnlyValue', $this->fieldType()->readOnlyValue( $this ), $this );

        // The rich text editors render their value as HTML (since 1.6.0)
        return $this->usesRichText() && is_string( $value ) ? RichText::sanitize( $value ) : $value;
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
     * Gives the field its form for reading form-level settings, without a query.
     *
     * Renderers that already hold the form call this for each field. The form
     * is not set as the `form` relation (see settingsForm()).
     *
     * @since 1.6.0
     *
     * @param  Form  $form  The field's form.
     *
     * @return static The field.
     */
    public function withSettingsForm( Form $form ): static
    {
        $this->settingsForm = $form;

        return $this;
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
     * Reads a sub-input flag from the field's settings, falling back to the type's definition.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $config  The sub-input's `field_config.sub_inputs` entry.
     * @param  array<string, mixed>  $definition  The sub-input's definition.
     * @param  string  $key  The flag.
     * @param  bool  $default  The value when neither sets it.
     *
     * @return bool The flag.
     */
    protected function subInputFlag( array $config, array $definition, string $key, bool $default ): bool
    {
        $value = $config[ $key ] ?? $definition[ $key ] ?? $default;

        return true === filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Reads a sub-input text setting from the field's settings, falling back to the type's definition.
     *
     * @since 1.6.0
     *
     * @param  array<string, mixed>  $config  The sub-input's `field_config.sub_inputs` entry.
     * @param  array<string, mixed>  $definition  The sub-input's definition.
     * @param  string  $key  The setting.
     *
     * @return string|null The trimmed text, or null when neither sets it.
     */
    protected function subInputText( array $config, array $definition, string $key ): ?string
    {
        foreach ( [$config[ $key ] ?? null, $definition[ $key ] ?? null] as $value ) {
            if ( is_scalar( $value ) && ! is_bool( $value ) && '' !== trim( (string) $value ) ) {
                return trim( (string) $value );
            }
        }

        return null;
    }

    /**
     * Normalizes a select sub-input's options to a list of value/label pairs.
     *
     * Takes value/label pairs or a value => label map; blank and repeated
     * values are dropped.
     *
     * @since 1.6.0
     *
     * @param  mixed  $options  The options.
     *
     * @return list<array{value: string, label: string}> The options.
     */
    protected static function normalizeSubInputOptions( mixed $options ): array
    {
        $normalized = [];

        foreach ( is_array( $options ) ? $options : [] as $key => $option ) {
            if ( is_array( $option ) ) {
                $value = $option['value'] ?? null;
                $label = $option['label'] ?? $value;
            } else {
                $value = is_string( $key ) ? $key : $option;
                $label = $option;
            }

            if ( ! is_scalar( $value ) || is_bool( $value ) || '' === trim( (string) $value ) || isset( $normalized[ (string) $value ] ) ) {
                continue;
            }

            $normalized[ (string) $value ] = ['value' => (string) $value, 'label' => is_scalar( $label ) ? (string) $label : (string) $value];
        }

        return array_values( $normalized );
    }

    /**
     * Checks whether a toggle setting stored in `field_config` is on.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The `field_config` key.
     *
     * @return bool True when on.
     */
    protected function configFlag( string $key ): bool
    {
        return true === filter_var( $this->getConfig( $key, false ), FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Gets a positive whole number stored in `field_config`.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The `field_config` key.
     *
     * @return int|null The number, or null when unset or not positive.
     */
    protected function positiveConfigInteger( string $key ): ?int
    {
        $value = $this->getConfig( $key );

        return is_numeric( $value ) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Gets a placement setting from the field, falling back to the form's `settings`.
     *
     * Types without the setting (such as hidden fields, which show no label)
     * take neither.
     *
     * @since 1.6.0
     *
     * @param  string  $key  The setting key, the same on the field and the form.
     * @param  list<string>  $allowed  The accepted values.
     *
     * @return string|null The placement, or null when neither sets a valid one.
     */
    protected function placementSetting( string $key, array $allowed ): ?string
    {
        if ( ! $this->offersSetting( $key ) ) {
            return null;
        }

        $placement = $this->getConfig( $key );

        if ( is_string( $placement ) && in_array( $placement, $allowed, true ) ) {
            return $placement;
        }

        $placement = $this->settingsForm()?->getSetting( $key );

        return is_string( $placement ) && in_array( $placement, $allowed, true ) ? $placement : null;
    }

    /**
     * Gets the field's form, for reading form-level settings.
     *
     * Uses the `form` relation when loaded. Otherwise the form is fetched once
     * and kept outside the relations, so a field loaded through its form's
     * `fields` relation does not point back at the form (which Livewire could
     * not dehydrate).
     *
     * @since 1.6.0
     *
     * @return Form|null The form.
     */
    protected function settingsForm(): ?Form
    {
        if ( $this->relationLoaded( 'form' ) ) {
            return $this->getRelation( 'form' );
        }

        if ( null === $this->form_id ) {
            return null;
        }

        if ( $this->settingsForm?->getKey() !== $this->form_id ) {
            $this->settingsForm = Form::query()->find( $this->form_id );
        }

        return $this->settingsForm;
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
