<?php

/**
 * Consent field type handler.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\FieldTypes;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;

/**
 * A consent checkbox, such as GDPR or terms acceptance, that records what
 * the visitor agreed to.
 *
 * The checkbox shows the field's `consent_text`; an optional
 * `consent_description` below it may hold links and basic formatting
 * (sanitized with kses). A required consent field must be checked
 * (`accepted`). The stored value is a map in `value_array`:
 *
 * - `consented`: `1` or `0`.
 * - `text` and `description`: the checkbox text and description as they
 *   were shown, unless `privacy.consent.store_text` is off.
 * - `revision`: a SHA-256 hash of the text and description, so a later
 *   edit can be told apart even when the text is not stored.
 * - `consented_at`: when the visitor submitted, in ISO 8601.
 *
 * Unchecked consent stores only `consented`. The value is shown as
 * "Consented" or "Not consented".
 *
 * @since 1.6.0
 */
class ConsentFieldType extends AbstractFieldType
{
    /**
     * The longest checkbox text an admin may enter.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const TEXT_MAX_LENGTH = 500;

    /**
     * The longest description an admin may enter.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DESCRIPTION_MAX_LENGTH = 5000;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'consent';

    /**
     * Gets the text shown beside the checkbox.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The consent field.
     *
     * @return string The field's `consent_text`, or the default text.
     */
    public static function checkboxText( FormField $field ): string
    {
        $text = $field->getConfig( 'consent_text' );

        return is_string( $text ) && '' !== trim( $text ) ? trim( $text ) : __( 'I agree to the privacy policy.' );
    }

    /**
     * Gets the description shown below the checkbox, as safe HTML.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The consent field.
     *
     * @return string The sanitized description, or an empty string.
     */
    public static function description( FormField $field ): string
    {
        $description = $field->getConfig( 'consent_description' );

        return is_string( $description ) && '' !== trim( $description ) ? FormField::sanitizeHtml( trim( $description ) ) : '';
    }

    /**
     * Gets the revision hash of the consent a field asks for.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The consent field.
     *
     * @return string A SHA-256 hash of the checkbox text and description.
     */
    public static function revision( FormField $field ): string
    {
        return hash( 'sha256', self::checkboxText( $field ) . "\n" . self::description( $field ) );
    }

    /**
     * Checks whether a stored consent value records consent.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The stored `value_array`, or a submitted value.
     *
     * @return bool True when consent was given.
     */
    public static function isConsented( mixed $value ): bool
    {
        if ( is_array( $value ) ) {
            $value = $value['consented'] ?? false;
        }

        return true === filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * {@inheritDoc}
     *
     * Records the consent text, revision and time with a checked box.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        if ( ! self::isConsented( $value ) ) {
            return ['consented' => '0'];
        }

        $consent = ['consented' => '1'];

        if ( true === filter_var( config( 'artisanpack.forms.privacy.consent.store_text', true ), FILTER_VALIDATE_BOOLEAN ) ) {
            $consent['text']        = self::checkboxText( $field );
            $consent['description'] = self::description( $field );
        }

        $consent['revision']     = self::revision( $field );
        $consent['consented_at'] = now()->toIso8601String();

        return $consent;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        $stored = is_array( $value->value_array ) ? $value->value_array : $value->value;

        return self::isConsented( $stored ) ? __( 'Consented' ) : __( 'Not consented' );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['consent'], [
            'label'    => __( 'Consent' ),
            'defaults' => [
                'label' => __( 'Consent' ),
            ],
            'settings' => [
                [
                    'key'         => 'consent_text',
                    'max_length'  => self::TEXT_MAX_LENGTH,
                    'label'       => __( 'Checkbox Text' ),
                    'placeholder' => __( 'I agree to the privacy policy.' ),
                    'hint'        => __( 'Stored with each submission, so you can tell what each visitor agreed to.' ),
                ],
                [
                    'key'        => 'consent_description',
                    'input'      => 'textarea',
                    'max_length' => self::DESCRIPTION_MAX_LENGTH,
                    'label'      => __( 'Consent Description' ),
                    'hint'       => __( 'Shown below the checkbox. Links and basic formatting are allowed.' ),
                ],
            ],
        ] );
    }

    /**
     * Gets the presence rule.
     *
     * A required consent box must be checked, as with a single checkbox.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return string `accepted` or `nullable`.
     */
    protected function presenceRule( FormField $field ): string
    {
        return $field->is_required ? 'accepted' : 'nullable';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['boolean'];
    }
}
