<?php

declare( strict_types=1 );

namespace Tests\Support;

use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;

/**
 * A composite type for the composite field tests: four sub-inputs, one of
 * them off by default and one an email input.
 */
class AddressFieldType extends CompositeFieldType
{
    /**
     * The type name this handler is registered under.
     */
    protected string $key = 'test_address';

    /**
     * {@inheritDoc}
     */
    protected function defineType(): array
    {
        return [
            'label'            => 'Test Address',
            'icon'             => 'o-map-pin',
            'format_separator' => ', ',
            'defaults'         => ['label' => 'Address'],
            'sub_inputs'       => [
                ['key' => 'street', 'label' => 'Street', 'autocomplete' => 'street-address', 'required' => true],
                ['key' => 'city', 'label' => 'City', 'autocomplete' => 'address-level2', 'required' => true],
                ['key' => 'line2', 'label' => 'Line 2', 'enabled' => false],
                ['key' => 'contact', 'label' => 'Contact email', 'type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'you@example.com'],
            ],
        ];
    }
}
