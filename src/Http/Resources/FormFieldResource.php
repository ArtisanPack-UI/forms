<?php

/**
 * Form field API resource.
 *
 * Transforms FormField model instances into JSON API responses.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Http\Resources;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\CalculationFieldType;
use ArtisanPackUI\Forms\FieldTypes\CaptchaFieldType;
use ArtisanPackUI\Forms\FieldTypes\ConsentFieldType;
use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\FieldTypes\MatrixFieldType;
use ArtisanPackUI\Forms\FieldTypes\NpsFieldType;
use ArtisanPackUI\Forms\FieldTypes\PhoneFieldType;
use ArtisanPackUI\Forms\FieldTypes\SignatureFieldType;
use ArtisanPackUI\Forms\FieldTypes\SliderFieldType;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\ChoiceImages;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\PopulationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Form field API resource class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.1.0
 */
class FormFieldResource extends JsonResource
{
    /**
     * Whether to include the field type's settings schema.
     *
     * @since 1.6.0
     */
    protected bool $includeSettingsSchema = true;

    /**
     * Whether to include the value the field starts with in the renderer.
     *
     * @since 1.6.0
     */
    protected bool $includeResolvedDefault = false;

    /**
     * Leaves the settings schema out, for payloads that only render the form.
     *
     * @since 1.6.0
     *
     * @return static The resource.
     */
    public function withoutSettingsSchema(): static
    {
        $this->includeSettingsSchema = false;

        return $this;
    }

    /**
     * Adds the value the field starts with in the renderer as `resolved_default_value`.
     *
     * Merge tags the server knows (`{user:…}`, `{date}`, `{ip}`) are replaced
     * and the `ap.forms.fieldValue.{parameter}` filter is applied; page tags
     * and the query string are left for the client, which knows the page.
     * Also adds the `label_placement` and `description_placement` set on the
     * field or its form (null when neither sets one).
     *
     * @since 1.6.0
     *
     * @return static The resource.
     */
    public function withResolvedDefault(): static
    {
        $this->includeResolvedDefault = true;

        return $this;
    }

    /**
     * Transforms the resource into an array.
     *
     * @since 1.1.0
     *
     * @param Request $request The incoming request.
     *
     * @return array<string, mixed> The transformed resource data.
     */
    public function toArray( Request $request ): array
    {
        return [
            'id'                => $this->id,
            'form_id'           => $this->form_id,
            'step_id'           => $this->step_id,
            'uuid'              => $this->uuid,
            'name'              => $this->name,
            'type'              => $this->type,
            'label'             => $this->label,
            'placeholder'       => $this->placeholder,
            'help_text'         => $this->help_text,
            'is_required'       => $this->is_required,
            'validation_rules'  => $this->validation_rules,
            'field_config'      => $this->sanitizedFieldConfig(),
            'default_value'     => 'html' === $this->type && null !== $this->default_value
                ? FormField::sanitizeHtml( $this->default_value )
                : $this->default_value,
            'conditional_logic'      => $this->conditional_logic,
            'width'                  => $this->width,
            'css_classes'            => $this->css_classes,
            'sort_order'             => $this->sort_order,
            'options'                => $this->when( FieldTypes::hasOptions( $this->type ) && ! empty( $this->options ), fn (): array => ChoiceImages::sanitizeOptions( $this->options ) ),
            'is_read_only'           => $this->resource->isReadOnly(),
            'read_only_value'        => $this->when( $this->resource->isReadOnly(), fn (): mixed => $this->resource->getReadOnlyValue() ),
            'autocomplete'           => $this->resource->getAutocomplete(),
            'error_message'          => $this->when( null !== $this->resource->getCustomErrorMessage(), fn (): ?string => $this->resource->getCustomErrorMessage() ),
            'required_message'       => $this->when( null !== $this->resource->getCustomRequiredMessage(), fn (): ?string => $this->resource->getCustomRequiredMessage() ),
            'dynamic_parameter'      => $this->resource->getPopulationParameter(),
            'is_administrative'      => $this->resource->isAdministrative(),
            'other_choice_label'     => $this->when( $this->resource->allowsOtherChoice(), fn (): string => $this->resource->getOtherChoiceLabel() ),
            'sub_inputs'             => $this->when( $this->resource->isComposite(), fn (): array => $this->subInputs() ),
            'required_sub_inputs'    => $this->when( $this->resource->isComposite(), fn (): array => $this->resource->getRequiredSubInputKeys() ),
            'sub_label_placement'    => $this->when( $this->resource->isComposite(), fn (): string => $this->resource->getSubLabelPlacement() ),
            'format_separator'       => $this->when( $this->resource->isComposite(), fn (): string => (string) ( $this->resource->fieldType()->definition()['format_separator'] ?? ', ' ) ),
            'email_confirmation'     => $this->when( $this->resource->requiresEmailConfirmation(), fn (): array => $this->resource->getEmailConfirmationLabels() ),
            'consent'                => $this->when( 'consent' === $this->type, fn (): array => [
                'text'        => ConsentFieldType::checkboxText( $this->resource ),
                'description' => ConsentFieldType::description( $this->resource ),
            ] ),
            'captcha'                => $this->when( CaptchaFieldType::TYPE === $this->type, fn (): ?array => CaptchaFieldType::widget( $this->resource ) ),
            'input_mask'             => $this->when( null !== $this->resource->getInputMask(), fn (): array => [
                'mask' => $this->resource->getInputMask(),
                'hint' => $this->resource->getInputMaskHint(),
            ] ),
            'affix_description'      => $this->when( null !== $this->resource->getAffixDescription(), fn (): ?string => $this->resource->getAffixDescription() ),
            'content_html'           => $this->when( 'paragraph' === $this->type, fn (): string => $this->resource->getParagraphHtml() ),
            'list'                   => $this->when( ListFieldType::TYPE === $this->type, fn (): array => [
                'columns'   => ListFieldType::columns( $this->resource ),
                'min_rows'  => ListFieldType::minRows( $this->resource ),
                'max_rows'  => ListFieldType::maxRows( $this->resource ),
                'row_label' => ListFieldType::rowLabel( $this->resource ),
            ] ),
            'slider'                 => $this->when( SliderFieldType::TYPE === $this->type, fn (): array => array_merge(
                SliderFieldType::limits( $this->resource ),
                SliderFieldType::affixes( $this->resource ),
                ['show_value' => SliderFieldType::showsValue( $this->resource )],
            ) ),
            'nps'                    => $this->when( NpsFieldType::TYPE === $this->type, fn (): array => NpsFieldType::endLabels( $this->resource ) ),
            'matrix'                 => $this->when( MatrixFieldType::TYPE === $this->type, fn (): array => [
                'single'  => MatrixFieldType::isSingle( $this->resource ),
                'rows'    => MatrixFieldType::rows( $this->resource ),
                'columns' => MatrixFieldType::columns( $this->resource ),
            ] ),
            'calculation'            => $this->when( CalculationFieldType::TYPE === $this->type, fn (): array => [
                'formula'  => CalculationFieldType::formula( $this->resource ),
                'decimals' => CalculationFieldType::decimals( $this->resource ),
            ] ),
            'signature'              => $this->when( SignatureFieldType::TYPE === $this->type, fn (): array => [
                'pad_height' => SignatureFieldType::padHeight( $this->resource ),
                'max_bytes'  => SignatureFieldType::MAX_BYTES,
            ] ),
            'phone'                  => $this->when( 'phone' === $this->type && 'any' !== $this->resource->getPhoneFormat(), fn (): array => [
                'format'          => $this->resource->getPhoneFormat(),
                'default_country' => $this->resource->getPhoneDefaultCountry(),
                'countries'       => $this->resource->usesPhoneCountrySelector() ? PhoneFieldType::countries() : [],
            ] ),
            'label_placement'        => $this->when( $this->includeResolvedDefault, fn (): ?string => $this->resource->getConfiguredLabelPlacement() ),
            'description_placement'  => $this->when( $this->includeResolvedDefault, fn (): ?string => $this->resource->getConfiguredDescriptionPlacement() ),
            'resolved_default_value' => $this->when(
                $this->includeResolvedDefault,
                fn (): mixed => DynamicPopulation::initialValue( $this->resource, PopulationContext::fromApiRequest( $request ) ),
            ),
            'settings_schema'   => $this->when( $this->includeSettingsSchema, fn (): array => FieldSettings::schema( $this->resource ) ),
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Gets a composite field's sub-inputs for the builders and renderers.
     *
     * Every sub-input is listed, with its `enabled` flag, so the builders can
     * turn them on and off; the renderers show the enabled ones. Validation
     * rules stay on the server.
     *
     * @since 1.6.0
     *
     * @return list<array{key: string, label: string, default_label: string, placeholder: string|null, enabled: bool, required: bool, autocomplete: string|null, type: string}> The sub-inputs.
     */
    protected function subInputs(): array
    {
        return array_values( array_map(
            fn ( array $subInput ): array => array_diff_key( $subInput, ['rules' => true] ),
            $this->resource->getSubInputs(),
        ) );
    }

    /**
     * Gets the field config with an html field's content and a consent field's description sanitized.
     *
     * The React and Vue renderers inject html content as raw HTML, so it is
     * sanitized here exactly like the legacy `default_value` copy. Option
     * images that are not safe to show are dropped (since 1.6.0; see
     * ChoiceImages).
     *
     * @since 1.6.0
     *
     * @return array<string, mixed>|null The field config.
     */
    protected function sanitizedFieldConfig(): ?array
    {
        $config = $this->field_config;

        if ( 'html' === $this->type && is_array( $config ) && is_string( $config['content'] ?? null ) ) {
            $config['content'] = FormField::sanitizeHtml( $config['content'] );
        }

        if ( 'consent' === $this->type && is_array( $config ) && is_string( $config['consent_description'] ?? null ) ) {
            $config['consent_description'] = FormField::sanitizeHtml( $config['consent_description'] );
        }

        // Option images must be safe to show (since 1.6.0)
        if ( is_array( $config ) && is_array( $config['options'] ?? null ) ) {
            $config['options'] = ChoiceImages::sanitizeOptions( $config['options'] );
        }

        return $config;
    }
}
