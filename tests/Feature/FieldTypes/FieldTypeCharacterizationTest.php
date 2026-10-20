<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\SubmissionService;

/**
 * Locks in the behaviour of the 19 field types that shipped before the
 * field-type handler contract (#91), so routing them through handlers is a
 * pure refactor. The expected values live in tests/Fixtures.
 */

$baseline = require __DIR__ . '/../../Fixtures/field-types-baseline.php';

/**
 * Field attributes used to exercise each type's rules and messages.
 *
 * @var array<string, array<string, mixed>>
 */
$samples = [
    'text'            => ['validation_rules' => ['min' => 2, 'max' => 10, 'pattern' => '/^a/']],
    'email'           => [],
    'url'             => [],
    'number'          => ['validation_rules' => ['min' => 0, 'max' => 5, 'step' => 0.5]],
    'phone'           => [],
    'textarea'        => [],
    'hidden'          => [],
    'select'          => [],
    'radio'           => [],
    'checkbox'        => [],
    'checkbox_group'  => ['validation_rules' => ['min' => 1]],
    'select_multiple' => [],
    'file'            => ['field_config' => ['max_size' => 100, 'allowed_types' => ['pdf']]],
    'date'            => ['validation_rules' => ['min_date' => '2026-01-01', 'max_date' => '2026-12-31']],
    'time'            => ['validation_rules' => ['min_time' => '09:00', 'max_time' => '17:00']],
    'heading'         => [],
    'paragraph'       => [],
    'divider'         => [],
    'html'            => [],
];

describe( 'built-in field type definitions', function () use ( $baseline ): void {
    it( 'keeps the metadata, label and defaults of each type', function ( string $type ) use ( $baseline ): void {
        $definition = FieldTypes::getTypeConfig( $type );

        foreach ( $baseline['types'][ $type ] as $key => $expected ) {
            expect( $definition )->toHaveKey( $key )
                ->and( $definition[ $key ] )->toBe( $expected );
        }
    } )->with( array_keys( $baseline['types'] ) );
} );

describe( 'built-in field type validation', function () use ( $baseline, $samples ): void {
    it( 'builds the same field rules', function ( string $type, bool $required ) use ( $baseline, $samples ): void {
        $field = FormField::factory()->create( array_merge(
            ['type' => $type, 'name' => $type, 'label' => ucfirst( $type ), 'is_required' => $required],
            $samples[ $type ],
        ) );

        $state = $required ? 'required' : 'optional';

        expect( $field->buildValidationRules() )->toBe( $baseline['rules'][ $type ][ $state ] );
    } )->with( array_keys( $samples ) )->with( [true, false] );

    it( 'builds the same submission rules and messages', function ( string $type, bool $required ) use ( $baseline, $samples ): void {
        $field = FormField::factory()->create( array_merge(
            ['type' => $type, 'name' => $type, 'label' => ucfirst( $type ), 'is_required' => $required],
            $samples[ $type ],
        ) );

        $service = app( SubmissionService::class );
        $state   = $required ? 'required' : 'optional';

        expect( $service->buildValidationRules( collect( [$field] ) ) )->toBe( $baseline['svcrules'][ $type ][ $state ] )
            ->and( $service->buildValidationMessages( collect( [$field] ) ) )->toBe( $baseline['messages'][ $type ][ $state ] );
    } )->with( array_keys( $samples ) )->with( [true, false] );
} );

describe( 'built-in field type storage, display and export', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create( ['name' => 'Contact'] );

        $this->fields = [
            'name'     => FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'sort_order' => 1] ),
            'color'    => FormField::factory()->for( $this->form )->create( [
                'type'         => 'select',
                'name'         => 'color',
                'label'        => 'Color',
                'sort_order'   => 2,
                'field_config' => ['options' => [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'Blue']]],
            ] ),
            'toppings' => FormField::factory()->for( $this->form )->create( [
                'type'         => 'checkbox_group',
                'name'         => 'toppings',
                'label'        => 'Toppings',
                'sort_order'   => 3,
                'field_config' => ['options' => [['value' => 'ham', 'label' => 'Ham'], ['value' => 'egg', 'label' => 'Egg']]],
            ] ),
            'agree'    => FormField::factory()->for( $this->form )->create( [
                'type'         => 'checkbox',
                'name'         => 'agree',
                'label'        => 'Agree',
                'sort_order'   => 4,
                'field_config' => ['checked_label' => 'Agreed'],
            ] ),
            'at'       => FormField::factory()->for( $this->form )->create( ['type' => 'time', 'name' => 'at', 'label' => 'At', 'sort_order' => 5] ),
            'intro'    => FormField::factory()->for( $this->form )->create( ['type' => 'heading', 'name' => 'intro', 'label' => 'Intro', 'sort_order' => 6] ),
            'resume'   => FormField::factory()->for( $this->form )->file()->create( ['name' => 'resume', 'label' => 'Resume', 'sort_order' => 7] ),
        ];

        $this->submission = FormSubmission::factory()->for( $this->form )->create();

        $values = [
            'name'     => ['value' => 'Ada'],
            'color'    => ['value' => 'blue'],
            'toppings' => ['value_array' => ['ham', 'egg']],
            'agree'    => ['value' => '1'],
            'at'       => ['value' => '09:30'],
        ];

        foreach ( $values as $name => $value ) {
            $field = $this->fields[ $name ];

            FormSubmissionValue::factory()->create( array_merge( [
                'submission_id' => $this->submission->id,
                'field_id'      => $field->id,
                'field_name'    => $field->name,
                'field_label'   => $field->label,
                'field_type'    => $field->type,
                'value'         => null,
                'value_array'   => null,
            ], $value ) );
        }

        $upload = FormUpload::factory()->create( [
            'submission_id' => $this->submission->id,
            'field_id'      => $this->fields['resume']->id,
            'original_name' => 'cv.pdf',
        ] );

        FormSubmissionValue::factory()->create( [
            'submission_id' => $this->submission->id,
            'field_id'      => $this->fields['resume']->id,
            'field_name'    => 'resume',
            'field_label'   => 'Resume',
            'field_type'    => 'file',
            'value'         => null,
            'value_array'   => null,
            'upload_id'     => $upload->id,
        ] );

        $this->submission->load( 'values.field', 'values.upload' );
    } );

    it( 'keeps the display values', function (): void {
        expect( $this->submission->getValue( 'name' ) )->toBe( 'Ada' )
            ->and( $this->submission->getValue( 'color' ) )->toBe( 'Blue' )
            ->and( $this->submission->getValue( 'toppings' ) )->toBe( 'Ham, Egg' )
            ->and( $this->submission->getValue( 'agree' ) )->toBe( 'Agreed' )
            ->and( $this->submission->getValue( 'at' ) )->toBe( '09:30' )
            ->and( $this->submission->getValue( 'resume' ) )->toBe( 'cv.pdf' );
    } );

    it( 'keeps one export column per data field, in order, with display values', function (): void {
        $service = app( ExportService::class );
        $headers = $service->buildHeaders( $this->form );
        $row     = $service->buildRow( $this->form, $this->submission, $this->form->fields()->orderBy( 'sort_order' )->get() );

        expect( array_slice( $headers, 3, 6 ) )->toBe( ['Name', 'Color', 'Toppings', 'Agree', 'At', 'Resume'] )
            ->and( array_slice( $row, 3, 6 ) )->toBe( ['Ada', 'Blue', 'Ham, Egg', 'Agreed', '09:30', 'cv.pdf'] );
    } );

    it( 'keeps one merge tag per field, labelled with the field label', function (): void {
        $placeholders = app( NotificationService::class )->getAvailablePlaceholders( $this->form );

        expect( $placeholders )->toMatchArray( [
            '{name}'     => 'Name',
            '{color}'    => 'Color',
            '{toppings}' => 'Toppings',
            '{agree}'    => 'Agree',
            '{at}'       => 'At',
            '{intro}'    => 'Intro',
            '{resume}'   => 'Resume',
        ] );
    } );

    it( 'replaces merge tags with display values', function (): void {
        $parsed = app( NotificationService::class )->parseTemplate( '{name} likes {color} with {toppings} ({agree})', $this->submission );

        expect( $parsed )->toBe( 'Ada likes Blue with Ham, Egg (Agreed)' );
    } );

    it( 'stores submitted values the same way', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'time', 'name' => 'at'] );
        FormField::factory()->for( $form )->create( ['type' => 'number', 'name' => 'qty'] );
        FormField::factory()->for( $form )->create( ['type' => 'checkbox_group', 'name' => 'tags'] );

        $submission = app( SubmissionService::class )->create( $form, [
            'at'   => '09:30:00',
            'qty'  => 3,
            'tags' => ['a', 'b'],
        ] );

        $values = $submission->values()->get()->keyBy( 'field_name' );

        expect( $values['at']->value )->toBe( '09:30' )
            ->and( $values['qty']->value )->toBe( '3' )
            ->and( $values['tags']->value_array )->toBe( ['a', 'b'] )
            ->and( $values['tags']->value )->toBeNull();
    } );
} );
