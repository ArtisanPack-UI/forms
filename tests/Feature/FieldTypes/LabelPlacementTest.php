<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Http\Resources\FormResource;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\FormService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Tests\Models\User;

/**
 * Guards issue #98: label placement (top, left, hidden) and description
 * placement (above, below), set per field with a form-level default.
 */
describe( 'label and description placement settings', function (): void {
    it( 'offers both placements on the General tab of input types, blank meaning the form default', function (): void {
        $text = FormField::factory()->make( ['type' => 'text'] );

        expect( FieldSettings::find( $text, 'label_placement' ) )->toMatchArray( ['tab' => 'general', 'input' => 'select', 'store' => 'field_config'] )
            ->and( array_column( FieldSettings::find( $text, 'label_placement' )['options'], 'value' ) )->toBe( ['', 'top', 'left', 'hidden'] )
            ->and( array_column( FieldSettings::find( $text, 'description_placement' )['options'], 'value' ) )->toBe( ['', 'above', 'below'] );
    } );

    it( 'is not offered on hidden or layout fields', function ( string $type ): void {
        $field = FormField::factory()->make( ['type' => $type] );

        expect( FieldSettings::find( $field, 'label_placement' ) )->toBeNull()
            ->and( FieldSettings::find( $field, 'description_placement' ) )->toBeNull();
    } )->with( ['hidden', 'heading', 'paragraph', 'divider', 'html'] );

    it( 'defaults to a top label and a description below the input', function (): void {
        $field = FormField::factory()->for( Form::factory() )->create( ['type' => 'text'] );

        expect( $field->getLabelPlacement() )->toBe( 'top' )
            ->and( $field->getDescriptionPlacement() )->toBe( 'below' )
            ->and( $field->getConfiguredLabelPlacement() )->toBeNull()
            ->and( $field->getConfiguredDescriptionPlacement() )->toBeNull();
    } );

    it( 'falls back to the form default and lets the field override it', function (): void {
        $form = Form::factory()->create( ['settings' => ['label_placement' => 'left', 'description_placement' => 'above']] );

        $inherits  = FormField::factory()->for( $form )->create( ['type' => 'text'] );
        $overrides = FormField::factory()->for( $form )->create( [
            'type'         => 'email',
            'field_config' => ['label_placement' => 'hidden', 'description_placement' => 'below'],
        ] );

        expect( $inherits->getLabelPlacement() )->toBe( 'left' )
            ->and( $inherits->getDescriptionPlacement() )->toBe( 'above' )
            ->and( $overrides->getLabelPlacement() )->toBe( 'hidden' )
            ->and( $overrides->getDescriptionPlacement() )->toBe( 'below' );
    } );

    it( 'ignores unknown placements', function (): void {
        $form  = Form::factory()->create( ['settings' => ['label_placement' => 'floating']] );
        $field = FormField::factory()->for( $form )->create( ['type' => 'text', 'field_config' => ['label_placement' => 'right']] );

        expect( $field->getLabelPlacement() )->toBe( 'top' );
    } );

    it( 'gives a hidden field no placement, even with a form default', function (): void {
        $form  = Form::factory()->create( ['settings' => ['label_placement' => 'hidden']] );
        $field = FormField::factory()->for( $form )->create( ['type' => 'hidden'] );

        expect( $field->getConfiguredLabelPlacement() )->toBeNull();
    } );

    it( 'reads the form a renderer hands it without querying again', function (): void {
        $form           = Form::factory()->create( ['settings' => ['label_placement' => 'left']] );
        $field          = FormField::factory()->for( $form )->create( ['type' => 'text'] );
        $form->settings = ['label_placement' => 'hidden'];

        expect( $field->withSettingsForm( $form )->getLabelPlacement() )->toBe( 'hidden' )
            ->and( $field->relationLoaded( 'form' ) )->toBeFalse();
    } );
} );

describe( 'form-level placement defaults', function (): void {
    it( 'are stored in the form settings, keeping the settings already there', function (): void {
        $form = Form::factory()->create( ['settings' => ['webhook' => ['enabled' => false]]] );

        $updated = app( FormService::class )->update( $form, ['label_placement' => 'left', 'description_placement' => 'above'] );

        expect( $updated->settings )->toBe( ['webhook' => ['enabled' => false], 'label_placement' => 'left', 'description_placement' => 'above'] );

        $cleared = app( FormService::class )->update( $updated, ['label_placement' => null, 'description_placement' => ''] );

        expect( $cleared->settings )->toBe( ['webhook' => ['enabled' => false]] );
    } );

    it( 'are accepted by the API and returned by the form resource', function (): void {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        $this->actingAs( $user )
            ->putJson( route( 'api.forms.update', $form ), ['label_placement' => 'hidden'] )
            ->assertOk()
            ->assertJsonPath( 'data.label_placement', 'hidden' )
            ->assertJsonPath( 'data.description_placement', null );

        $this->actingAs( $user )
            ->putJson( route( 'api.forms.update', $form ), ['label_placement' => 'floating'] )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( 'label_placement' );
    } );

    it( 'are saved from the settings page', function (): void {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        $this->actingAs( $user )
            ->put( route( 'forms.update', $form ), [
                'name'                  => $form->name,
                'label_placement'       => 'left',
                'description_placement' => 'above',
            ] )
            ->assertRedirect();

        expect( $form->fresh()->getSetting( 'label_placement' ) )->toBe( 'left' )
            ->and( $form->fresh()->getSetting( 'description_placement' ) )->toBe( 'above' );
    } );

    it( 'resolve into the public render payload with each field', function (): void {
        $form = Form::factory()->create( ['settings' => ['description_placement' => 'above']] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'name', 'field_config' => ['label_placement' => 'left']] );
        FormField::factory()->for( $form )->create( ['type' => 'email', 'name' => 'email', 'sort_order' => 2] );

        $payload = ( new FormRenderResource( $form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) );
        $fields  = collect( $payload['fields']->resolve() )->keyBy( 'name' );

        expect( $fields['name'] )->toMatchArray( ['label_placement' => 'left', 'description_placement' => 'above'] )
            ->and( $fields['email'] )->toMatchArray( ['label_placement' => null, 'description_placement' => 'above'] )
            ->and( ( new FormResource( $form ) )->toArray( Request::create( '/' ) ) )->toMatchArray( ['label_placement' => null, 'description_placement' => 'above'] );
    } );
} );

describe( 'Blade placement rendering', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
    } );

    it( 'keeps the input component label for a top label and renders the description with its ID', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'city', 'label' => 'City', 'help_text' => 'Where you live'] );
        $html  = FieldRenderer::render( $field );

        expect( $html )->not->toContain( 'id="label-city"' )
            ->toContain( 'id="hint-city"' )
            ->toContain( 'aria-describedby="hint-city"' );
    } );

    it( 'renders a hidden label sr-only, still naming the input', function (): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'text',
            'name'         => 'city',
            'label'        => 'City',
            'field_config' => ['label_placement' => 'hidden'],
        ] );
        $html = FieldRenderer::render( $field );

        expect( $html )->toMatch( '/<label\s+id="label-city"\s+class="label sr-only"/' )
            ->toContain( 'aria-labelledby="label-city"' );
    } );

    it( 'places a left label beside the input from the md breakpoint up', function (): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'date',
            'name'         => 'start',
            'label'        => 'Start',
            'field_config' => ['label_placement' => 'left'],
        ] );
        $html = FieldRenderer::render( $field );

        expect( $html )->toContain( 'md:grid md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]' )
            ->toContain( 'for="field-start"' )
            ->toContain( 'md:col-start-2' );
    } );

    it( 'puts the description above or below the input, keeping it referenced', function ( string $placement ): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'time',
            'name'         => 'arrival',
            'label'        => 'Arrival',
            'help_text'    => 'Local time',
            'field_config' => ['description_placement' => $placement],
        ] );
        $html = FieldRenderer::render( $field );

        $hint  = strpos( $html, 'id="hint-arrival"' );
        $input = strpos( $html, 'type="time"' );

        expect( $html )->toContain( 'aria-describedby="hint-arrival"' )
            ->and( 'above' === $placement ? $hint < $input : $hint > $input )->toBeTrue();
    } )->with( ['above', 'below'] );

    it( 'names a radio group by its hidden label', function (): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'radio',
            'name'         => 'size',
            'label'        => 'Size',
            'field_config' => ['label_placement' => 'hidden', 'options' => [['value' => 's', 'label' => 'Small']]],
        ] );
        $html = FieldRenderer::render( $field );

        expect( $html )->toContain( 'class="label sr-only"' )
            ->toContain( 'aria-labelledby="label-size"' );
    } );

    it( 'hides a checkbox label for screen readers only', function (): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'checkbox',
            'name'         => 'terms',
            'label'        => 'I agree',
            'field_config' => ['label_placement' => 'hidden'],
        ] );
        $html = FieldRenderer::render( $field );

        expect( $html )->toContain( 'class="label-text sr-only"' )
            ->toContain( 'I agree' );
    } );
} );
