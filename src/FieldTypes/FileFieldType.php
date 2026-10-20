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

/**
 * File upload.
 *
 * @since 1.6.0
 */
class FileFieldType extends AbstractFieldType
{
    /**
     * The type name this handler is registered under.
     *
     * @since 1.6.0
     */
    protected string $key = 'file';

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

        return $rules;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.6.0
     */
    protected function typeMessages( FormField $field ): array
    {
        return [
            'file'  => __( 'Please upload a valid file for :field.', ['field' => $field->label] ),
            'mimes' => __( 'The :field must be a file of the allowed types.', ['field' => $field->label] ),
            'max'   => __( 'The :field must not exceed the maximum file size.', ['field' => $field->label] ),
        ];
    }
}
