<?php

/**
 * File field type handler.
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

/**
 * File upload.
 *
 * With `multiple` on, the field takes a list of files, up to `max_files`
 * and `max_total_size`. Each file is stored as its own `form_uploads` row;
 * the submission value points at the first in `upload_id` and lists them
 * all in `value_array` (see FormSubmissionValue::getUploads()).
 *
 * The input's note lists the allowed types next to the size limits (see
 * FormField::getFileLimitsHint()). With the `storage` setting on
 * `media_library`, files are saved with artisanpack-ui/media-library (see
 * MediaLibraryStorage) and their `form_uploads` rows point at the media item.
 *
 * @since 1.6.0
 */
class FileFieldType extends AbstractFieldType
{

    /**
     * The most files a multi-file field may be set to accept.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_FILES = 100;

    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'file';

    /**
     * {@inheritDoc}
     *
     * A multi-file field's value is a list of files: the list is checked
     * for its count and total size, and each file (`*`) like a single upload.
     *
     * @since 1.6.0
     */
    public function rules( FormField $field ): array
    {
        if ( ! $field->allowsMultipleFiles() ) {
            return parent::rules( $field );
        }

        $listRules = [$this->presenceRule( $field ), 'array'];

        if ( null !== ( $maxCount = $field->getFileMaxCount() ) ) {
            $listRules[] = "max:{$maxCount}";
        }

        if ( null !== ( $maxTotal = $field->getFileMaxTotalSize() ) ) {
            $listRules[] = SubmissionRules::TOTAL_SIZE . ':' . $maxTotal;
        }

        return [
            ''  => $listRules,
            '*' => $this->typeRules( $field ),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    public function messages( FormField $field ): array
    {
        $messages = parent::messages( $field );

        if ( ! $field->allowsMultipleFiles() ) {
            return $messages;
        }

        $fileMessages = $this->typeMessages( $field );
        $listMessages = [
            'array'                      => __( 'Please upload valid files for :field.', ['field' => $field->label] ),
            'max'                        => __( 'You may upload no more than :max files for :field.', ['field' => $field->label, 'max' => $field->getFileMaxCount()] ),
            SubmissionRules::TOTAL_SIZE  => __( 'The files for :field must not exceed :size KB in total.', ['field' => $field->label, 'size' => $field->getFileMaxTotalSize()] ),
        ];

        foreach ( $fileMessages as $rule => $message ) {
            $listMessages[ "*.{$rule}" ] = $message;
        }

        return array_merge( array_diff_key( $messages, $fileMessages ), $listMessages );
    }

    /**
     * {@inheritDoc}
     *
     * Lists every file of a multi-file value.
     *
     * @since 1.6.0
     */
    public function display( FormSubmissionValue $value ): string
    {
        $uploads = $value->getUploads();

        return [] === $uploads ? '' : implode( ', ', array_map( fn ( FormUpload $upload ): string => $upload->original_name, $uploads ) );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function defineType(): array
    {
        return array_merge( FieldTypes::TYPE_METADATA['file'], [
            'label'    => __( 'File Upload' ),
            'defaults' => [
                'label'        => __( 'Upload File' ),
                'field_config' => [
                    'max_size'      => 5120,
                    'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'png'],
                ],
            ],
            'settings' => [
                [
                    'key'          => 'max_size',
                    'input'        => 'integer',
                    'legacy_store' => 'validation_rules',
                    'tab'          => 'validation',
                    'min'          => 1,
                    'label'        => __( 'Max File Size (KB)' ),
                    'placeholder'  => '5120',
                    'hint'         => __( 'Default: 5120 KB (5 MB)' ),
                ],
                [
                    'key'          => 'allowed_types',
                    'input'        => 'list',
                    'legacy_store' => 'validation_rules',
                    'tab'          => 'validation',
                    'label'        => __( 'Allowed File Types' ),
                    'placeholder'  => 'pdf, doc, docx, jpg, png',
                    'hint'         => __( 'Comma-separated file extensions' ),
                    'monospace'    => true,
                ],
                [
                    'key'   => 'multiple',
                    'input' => 'toggle',
                    'label' => __( 'Allow multiple files' ),
                    'hint'  => __( 'Shows a drop zone that takes several files, each with its own progress and remove button.' ),
                ],
                ['key' => 'max_files', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'max' => self::MAX_FILES, 'label' => __( 'Max Files' ), 'placeholder' => __( 'Unlimited' ), 'hint' => __( 'For multiple files.' )],
                ['key' => 'max_total_size', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'label' => __( 'Max Total Size (KB)' ), 'placeholder' => __( 'Unlimited' ), 'hint' => __( 'For multiple files: the limit for all files together.' )],
                ['key' => 'min_width', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'label' => __( 'Min Image Width (px)' )],
                ['key' => 'max_width', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'label' => __( 'Max Image Width (px)' )],
                ['key' => 'min_height', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'label' => __( 'Min Image Height (px)' )],
                ['key' => 'max_height', 'input' => 'integer', 'tab' => 'validation', 'min' => 1, 'label' => __( 'Max Image Height (px)' ), 'hint' => __( 'Image bounds apply to images only; other allowed files are not checked.' )],
                [
                    'key'     => 'storage',
                    'input'   => 'select',
                    'tab'     => 'advanced',
                    'default' => 'forms',
                    'label'   => __( 'Storage' ),
                    'hint'    => __( 'Media Library needs artisanpack-ui/media-library. Its files follow the media library disk\'s visibility, which is public by default; files it cannot take stay on the forms disk.' ),
                    'options' => [
                        ['value' => 'forms', 'label' => __( 'Forms disk (private)' )],
                        ['value' => 'media_library', 'label' => __( 'Media Library' )],
                    ],
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
        $rules = ['file'];

        if ( $types = $field->getFileAllowedTypes() ) {
            $rules[] = 'mimes:' . implode( ',', $types );
        }

        if ( $maxSize = $field->getFileMaxSize() ) {
            $rules[] = "max:{$maxSize}";
        }

        $dimensions = $field->getImageDimensionLimits();

        if ( [] !== array_filter( $dimensions ) ) {
            $rules[] = SubmissionRules::IMAGE_DIMENSIONS . ':' . implode( ',', array_map( fn ( ?int $bound ): string => (string) $bound, $dimensions ) );
        }

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        $messages = [
            'file'  => __( 'Please upload a valid file for :field.', ['field' => $field->label] ),
            'mimes' => __( 'The :field must be a file of the allowed types.', ['field' => $field->label] ),
            'max'   => __( 'The :field must not exceed the maximum file size.', ['field' => $field->label] ),
        ];

        if ( [] !== array_filter( $field->getImageDimensionLimits() ) ) {
            $messages[ SubmissionRules::IMAGE_DIMENSIONS ] = __( 'The :field image is outside the allowed dimensions.', ['field' => $field->label] );
        }

        return $messages;
    }
}
