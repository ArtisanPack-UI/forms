<?php

/**
 * Text area field type handler.
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
use ArtisanPackUI\Forms\Support\RichText;
use ArtisanPackUI\Forms\Support\SubmissionRules;

/**
 * Multi-line text input.
 *
 * With `rich_text` on, visitors write in a small rich text editor and the
 * field submits HTML. The HTML is sanitized with `kses()` when stored and
 * again when shown (see RichText); length and word limits count its text.
 * The display value is the plain text, for text emails, exports and lists;
 * HTML views read FormSubmissionValue::display_html instead.
 *
 * @since 1.6.0
 */
class TextareaFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'textarea';

    /**
     * {@inheritDoc}
     *
     * Sanitizes rich text HTML.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return $field->usesRichText() && is_string( $value ) ? RichText::sanitize( $value ) : $value;
    }

    /**
     * {@inheritDoc}
     *
     * Gives a rich text value's HTML, sanitized again.
     *
     * @since 1.6.0
     */
    public function displayHtml( FormSubmissionValue $value ): ?string
    {
        $field = $this->sourceField( $value );

        if ( null === $field || ! $field->usesRichText() ) {
            return null;
        }

        return RichText::sanitize( (string) ( $value->value ?? '' ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['textarea'], [
            'label'    => __( 'Text Area' ),
            'defaults' => [
                'label'        => __( 'Message' ),
                'placeholder'  => '',
                'field_config' => ['rows' => 4],
            ],
            'settings' => [
                ['key' => 'rows', 'input' => 'integer', 'min' => 1, 'max' => 50, 'default' => 4, 'placeholder' => '4', 'label' => __( 'Rows' )],
                [
                    'key'   => 'rich_text',
                    'input' => 'toggle',
                    'label' => __( 'Use rich text editor' ),
                    'hint'  => __( 'Lets visitors format text with bold, italic, lists and links. Other markup is removed.' ),
                ],
                $this->counterSetting(),
                ...$this->lengthSettings(),
                [
                    'key'   => 'max_words',
                    'input' => 'integer',
                    'store' => 'validation_rules',
                    'tab'   => 'validation',
                    'min'   => 1,
                    'label' => __( 'Max Words' ),
                    'hint'  => __( 'The counter counts words instead of characters when set.' ),
                ],
                $this->readOnlySetting(),
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * Rich text limits count the text rather than the markup, with the
     * `forms_min_text` and `forms_max_text` rules; the markup itself is
     * capped at RichText::MAX_HTML_LENGTH characters.
     *
     * @since 1.6.0
     */
    protected function limitRules( FormField $field ): array
    {
        if ( ! $field->usesRichText() ) {
            return parent::limitRules( $field );
        }

        $rules = ['max:' . RichText::MAX_HTML_LENGTH];

        foreach ( ['min' => SubmissionRules::MIN_TEXT, 'max' => SubmissionRules::MAX_TEXT] as $key => $rule ) {
            if ( null !== ( $limit = $this->validationLimit( $field, $key ) ) ) {
                $rules[] = "{$rule}:{$limit}";
            }
        }

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * Counts the words of rich text without its markup.
     *
     * @since 1.6.0
     */
    protected function settingRules( FormField $field ): array
    {
        $rules = parent::settingRules( $field );

        if ( ! $field->usesRichText() ) {
            return $rules;
        }

        return array_map(
            fn ( mixed $rule ): mixed => is_string( $rule ) && str_starts_with( $rule, SubmissionRules::MAX_WORDS . ':' ) ? "{$rule},html" : $rule,
            $rules,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        if ( ! $field->usesRichText() ) {
            return [];
        }

        return [
            'max'                     => __( 'The :field is too long.', ['field' => $field->label] ),
            SubmissionRules::MIN_TEXT => __( 'The :field must be at least :min characters.', ['field' => $field->label, 'min' => $this->validationLimit( $field, 'min' ) ?? ''] ),
            SubmissionRules::MAX_TEXT => __( 'The :field may not be greater than :max characters.', ['field' => $field->label, 'max' => $this->validationLimit( $field, 'max' ) ?? ''] ),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Shows rich text as plain text, with list items marked. The HTML is
     * sanitized first, so the text of removed elements such as scripts is
     * not shown.
     *
     * @since 1.6.0
     */
    protected function displayScalar( FormSubmissionValue $value ): string
    {
        $stored = $value->value ?? '';
        $field  = $this->sourceField( $value );

        return null !== $field && $field->usesRichText() ? RichText::toPlainText( RichText::sanitize( $stored ), true ) : $stored;
    }
}
