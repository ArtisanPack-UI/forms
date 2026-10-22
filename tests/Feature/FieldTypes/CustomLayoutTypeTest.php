<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\NotificationEditor;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Livewire\Livewire;

/**
 * Guards issue #88: a custom layout type registered through
 * `ap.forms.fieldTypes` with `is_layout => true` is skipped like the
 * built-in layout types.
 */
describe( 'custom layout field types', function (): void {
    beforeEach( function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['section_break'] = ['label' => 'Section Break', 'category' => 'layout', 'is_layout' => true];

            return $types;
        } );

        $this->form         = Form::factory()->create();
        $this->sectionBreak = FormField::factory()->for( $this->form )->required()->create( [
            'type'         => 'section_break',
            'name'         => 'gap',
            'field_config' => ['error_message' => 'Should never be used'],
        ] );
        $this->name = FormField::factory()->for( $this->form )->required()->create( ['type' => 'text', 'name' => 'name'] );
    } );

    afterEach( function (): void {
        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'gets no validation rules or messages', function (): void {
        $service = app( SubmissionService::class );
        $fields  = $this->form->fields()->get();

        expect( $service->buildValidationRules( $fields ) )->toHaveKey( 'formData.name' )
            ->not->toHaveKey( 'formData.gap' )
            ->and( array_keys( $service->buildValidationMessages( $fields ) ) )
            ->each->not->toStartWith( 'formData.gap.' );
    } );

    it( 'gets none of the input settings', function (): void {
        expect( array_column( FieldSettings::schema( $this->sectionBreak ), 'key' ) )->toBe( [] );
    } );

    it( 'gets none of the input settings when is_layout is truthy but not true', function ( mixed $isLayout ): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ) use ( $isLayout ): array {
            $types['spacer'] = ['label' => 'Spacer', 'category' => 'layout', 'is_layout' => $isLayout];

            return $types;
        } );

        $spacer = FormField::factory()->make( ['type' => 'spacer'] );

        expect( array_column( FieldSettings::schema( $spacer ), 'key' ) )->toBe( [] );
    } )->with( [1, '1'] );

    it( 'is not offered as a notification condition field', function (): void {
        $fields = Livewire::test( NotificationEditor::class, ['form' => $this->form] )
            ->instance()
            ->availableConditionFields();

        expect( $fields->pluck( 'name' )->all() )->toBe( ['name'] );
    } );
} );
