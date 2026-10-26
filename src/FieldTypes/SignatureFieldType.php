<?php

/**
 * Signature field type handler.
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
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A signature for waivers and agreements, drawn on a canvas pad or typed.
 *
 * A drawn signature is submitted as a PNG data URL
 * (`data:image/png;base64,…`) and stored as a `form_uploads` row, which the
 * submission value points at with `upload_id` (see
 * SubmissionService::handleSignature()). Visitors who can't draw type their
 * full name instead, which is stored as text. The `forms_signature` rule
 * checks both forms (see isValidSignature()). A drawn signature is shown as
 * an image in the submission detail and embedded in HTML notifications.
 *
 * @since 1.6.0
 */
class SignatureFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const TYPE = 'signature';

    /**
     * The prefix of a drawn signature's data URL.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const DATA_URL_PREFIX = 'data:image/png;base64,';

    /**
     * The largest drawn signature accepted, in bytes of PNG.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_BYTES = 512 * 1024;

    /**
     * The widest or tallest drawn signature accepted, in pixels.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_DIMENSION = 4000;

    /**
     * The longest typed signature accepted.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const TYPED_MAX_LENGTH = 255;

    /**
     * The name a drawn signature's upload is stored under.
     *
     * @since 1.6.0
     *
     * @var string
     */
    public const UPLOAD_NAME = 'signature.png';

    /**
     * The pad heights the renderers accept, in pixels.
     *
     * @since 1.6.0
     *
     * @var list<int>
     */
    public const PAD_HEIGHTS = [120, 160, 200, 240];

    /**
     * The pad height used when the field sets none.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const DEFAULT_PAD_HEIGHT = 160;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'signature';

    /**
     * Checks whether a submitted value is a drawn signature's data URL.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     *
     * @return bool True for a `data:image/png;base64,` string.
     */
    public static function isDrawn( mixed $value ): bool
    {
        return is_string( $value ) && str_starts_with( $value, self::DATA_URL_PREFIX );
    }

    /**
     * Decodes a drawn signature's PNG.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted data URL.
     *
     * @return string|null The PNG bytes, or null when the value is not a PNG of at most MAX_BYTES and MAX_DIMENSION pixels a side.
     */
    public static function decode( mixed $value ): ?string
    {
        if ( ! self::isDrawn( $value ) ) {
            return null;
        }

        $encoded = substr( $value, strlen( self::DATA_URL_PREFIX ) );

        // Base64 grows the data by a third, so reject oversized input before decoding it
        if ( strlen( $encoded ) > (int) ceil( self::MAX_BYTES / 3 ) * 4 ) {
            return null;
        }

        $png = base64_decode( $encoded, true );

        if ( false === $png || '' === $png || strlen( $png ) > self::MAX_BYTES || ! str_starts_with( $png, "\x89PNG\r\n\x1A\n" ) ) {
            return null;
        }

        // Read the header only: a small file can declare a huge image that would hang the browser showing it
        $size = @getimagesizefromstring( $png );

        if ( false === $size || IMAGETYPE_PNG !== $size[2] || $size[0] < 1 || $size[1] < 1 || $size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION ) {
            return null;
        }

        return $png;
    }

    /**
     * Checks whether a submitted value is a drawn signature or a typed name.
     *
     * A drawn signature must decode to a PNG (see decode()); a typed one must
     * be a name of at most TYPED_MAX_LENGTH characters, without control
     * characters. An empty value passes, leaving it to `required`.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The submitted value.
     *
     * @return bool True when valid.
     */
    public static function isValidSignature( mixed $value ): bool
    {
        if ( null === $value || '' === $value ) {
            return true;
        }

        if ( ! is_string( $value ) ) {
            return false;
        }

        if ( str_starts_with( $value, 'data:' ) ) {
            return null !== self::decode( $value );
        }

        return '' !== trim( $value )
            && mb_strlen( $value ) <= self::TYPED_MAX_LENGTH
            && 1 !== preg_match( '/[\x00-\x1F\x7F]/u', $value );
    }

    /**
     * Gets the height of a signature field's drawing pad.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return int The height in pixels, one of PAD_HEIGHTS.
     */
    public static function padHeight( FormField $field ): int
    {
        $height = $field->getConfig( 'pad_height' );

        return is_numeric( $height ) && in_array( (int) $height, self::PAD_HEIGHTS, true ) ? (int) $height : self::DEFAULT_PAD_HEIGHT;
    }

    /**
     * Gets the upload a drawn signature value points at.
     *
     * @since 1.6.0
     *
     * @param  FormSubmissionValue  $value  The stored value.
     *
     * @return FormUpload|null The PNG upload, or null for a typed or missing signature.
     */
    public static function upload( FormSubmissionValue $value ): ?FormUpload
    {
        if ( self::TYPE !== $value->field_type || null === $value->upload_id || $value->isRedacted() ) {
            return null;
        }

        return $value->upload;
    }

    /**
     * Reads a drawn signature's PNG from its disk.
     *
     * @since 1.6.0
     *
     * @param  FormUpload  $upload  The signature's upload.
     *
     * @return string|null The PNG bytes, or null when the file can't be read.
     */
    public static function contents( FormUpload $upload ): ?string
    {
        try {
            $contents = Storage::disk( $upload->disk )->get( $upload->path );
        } catch ( Throwable ) {
            return null;
        }

        return is_string( $contents ) && '' !== $contents ? $contents : null;
    }

    /**
     * Builds the `<img>` that shows a drawn signature.
     *
     * @since 1.6.0
     *
     * @param  string  $src  The image source: a data URL, or a `cid:` reference in an email.
     * @param  string  $label  The field label, for the image's alternative text.
     *
     * @return string The escaped HTML.
     */
    public static function imageHtml( string $src, string $label ): string
    {
        return '<img src="' . e( $src ) . '" alt="' . e( __( 'Signature for :field', ['field' => $label] ) ) . '" style="max-width: 100%; max-height: 160px; height: auto; background: #fff;" />';
    }

    /**
     * {@inheritDoc}
     *
     * A typed signature is trimmed. A drawn one is kept as submitted, since
     * SubmissionService stores it as an upload.
     *
     * @since 1.6.0
     */
    public function normalize( FormField $field, mixed $value ): mixed
    {
        return is_string( $value ) && ! self::isDrawn( $value ) ? trim( $value ) : $value;
    }

    /**
     * {@inheritDoc}
     *
     * A drawn signature reads "Signed (drawn)" and a typed one "Signed as …".
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        if ( null !== $value->upload_id ) {
            return __( 'Signed (drawn)' );
        }

        $typed = $value->value ?? '';

        return '' === $typed ? '' : __( 'Signed as ":name" (typed)', ['name' => $typed] );
    }

    /**
     * {@inheritDoc}
     *
     * A drawn signature as an image with the PNG inline, so it shows without
     * a download route wherever the value is shown; a typed one as text.
     *
     * @since 1.6.0
     */
    public function displayHtml( FormSubmissionValue $value ): ?string
    {
        $upload = self::upload( $value );

        if ( null === $upload ) {
            return null;
        }

        $png = self::contents( $upload );

        if ( null === $png ) {
            return e( __( 'Signed (drawn); the image could not be read.' ) );
        }

        return self::imageHtml( 'data:image/png;base64,' . base64_encode( $png ), (string) ( $this->sourceField( $value )?->label ?? $value->field_label ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function readOnlyValue( FormField $field ): mixed
    {
        return '';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['signature'], [
            'label'    => __( 'Signature' ),
            'defaults' => [
                'label' => __( 'Signature' ),
            ],
            'settings' => [
                [
                    'key'     => 'pad_height',
                    'input'   => 'select',
                    'default' => (string) self::DEFAULT_PAD_HEIGHT,
                    'label'   => __( 'Pad Height' ),
                    'hint'    => __( 'Visitors can also type their full name instead of drawing.' ),
                    'options' => array_map(
                        fn ( int $height ): array => ['value' => (string) $height, 'label' => __( ':height px', ['height' => $height] )],
                        self::PAD_HEIGHTS,
                    ),
                ],
            ],
        ] );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeRules( FormField $field ): array
    {
        return ['string', SubmissionRules::SIGNATURE];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        return [
            'string'                    => __( 'Please sign :field.', ['field' => $field->label] ),
            SubmissionRules::SIGNATURE  => __( 'Please draw your signature for :field, or type your full name (up to :max characters).', ['field' => $field->label, 'max' => self::TYPED_MAX_LENGTH] ),
        ];
    }
}
