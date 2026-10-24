<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FieldTypes\PhoneFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #117: phone formats, the country selector and E.164 storage.
 */
beforeEach( function (): void {
    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->phone = FormField::factory()->for( $this->form )->create( [
        'type'         => 'phone',
        'name'         => 'phone',
        'label'        => 'Phone',
        'field_config' => ['phone_format' => 'us'],
    ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.forms.submissionData' );
} );

describe( 'settings', function (): void {
    it( 'offers the format and default country', function (): void {
        $keys = array_column( FieldSettings::schema( $this->phone ), 'key' );

        expect( $keys )->toContain( 'phone_format', 'default_country' )
            ->and( array_column( FieldSettings::find( $this->phone, 'default_country' )['options'], 'value' ) )->toContain( 'US', 'GB', 'DE' );
    } );

    it( 'defaults to any format and the US', function (): void {
        $field = FormField::factory()->make( ['type' => 'phone', 'name' => 'p'] );

        expect( $field->getPhoneFormat() )->toBe( 'any' )
            ->and( $field->getPhoneDefaultCountry() )->toBe( 'US' )
            ->and( $field->usesPhoneCountrySelector() )->toBeFalse()
            ->and( $field->buildValidationRules() )->not->toContain( 'phone:US' );
    } );

    it( 'lists countries with their calling codes', function (): void {
        $countries = collect( PhoneFieldType::countries() )->keyBy( 'value' );

        expect( $countries['GB'] )->toBe( ['value' => 'GB', 'label' => 'United Kingdom (+44)', 'dial_code' => '44'] )
            ->and( $countries['US']['dial_code'] )->toBe( '1' );
    } );
} );

describe( 'validation', function (): void {
    it( 'builds the phone rule for each format', function (): void {
        expect( $this->phone->buildValidationRules() )->toContain( 'phone:US' );

        $this->phone->update( ['field_config' => ['phone_format' => 'international', 'default_country' => 'GB']] );

        expect( $this->phone->fresh()->buildValidationRules() )->toContain( 'phone:INTERNATIONAL,GB' );
    } );

    it( 'rejects an invalid US number and stores a valid one in E.164 form', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.phone', '12345' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.phone' => 'phone'] )
            ->assertSee( 'Please enter a valid phone number for Phone.' )
            ->set( 'formData.phone', '(201) 555-0123' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '+12015550123' );
    } );

    it( 'reads a national number in the selected country and drops the country', function (): void {
        $this->phone->update( ['field_config' => ['phone_format' => 'international', 'default_country' => 'US']] );
        $seen = null;
        addFilter( 'ap.forms.submissionData', function ( array $data ) use ( &$seen ): array {
            $seen = $data;

            return $data;
        } );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->assertSet( 'formData.phone_country', 'US' )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.phone_country', 'GB' )
            ->set( 'formData.phone', '020 7946 0958' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->pluck( 'value' )->all() )->toBe( ['+442079460958'] )
            ->and( $seen )->toBe( ['phone' => '+442079460958'] );
    } );

    it( 'accepts a number with its own country code whatever country is selected', function (): void {
        $this->phone->update( ['field_config' => ['phone_format' => 'international']] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['phone' => '+44 20 7946 0958'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '+442079460958' );
    } );

    it( 'rejects an invalid international number through the API', function (): void {
        $this->phone->update( ['field_config' => ['phone_format' => 'international']] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['phone' => '+44 12'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.phone' );
    } );

    it( 'keeps any-format numbers as typed', function (): void {
        $this->phone->update( ['field_config' => []] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['phone' => 'ext. 42'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( 'ext. 42' );
    } );

    it( 'keeps a real field that happens to use the country key', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'phone_country', 'label' => 'Other'] );

        $data = app( SubmissionService::class )->withoutTransientInputs( $this->form->fields()->get(), ['phone' => '+12015550123', 'phone_country' => 'kept'] );

        expect( $data )->toBe( ['phone' => '+12015550123', 'phone_country' => 'kept'] );
    } );
} );

describe( 'display', function (): void {
    it( 'shows a US number in national form and others in international form', function (): void {
        $value = fn ( string $stored, FormField $field ): FormSubmissionValue => FormSubmissionValue::make( [
            'field_id'   => $field->id,
            'field_type' => 'phone',
            'value'      => $stored,
        ] )->setRelation( 'field', $field );

        expect( $value( '+12015550123', $this->phone )->display_value )->toBe( '(201) 555-0123' );

        $this->phone->update( ['field_config' => ['phone_format' => 'international']] );

        expect( $value( '+442079460958', $this->phone->fresh() )->display_value )->toBe( '+44 20 7946 0958' )
            ->and( $value( 'not a number', $this->phone->fresh() )->display_value )->toBe( 'not a number' );
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders the country selector for an international field', function (): void {
        $this->phone->update( ['field_config' => ['phone_format' => 'international', 'default_country' => 'GB']] );
        $html = FieldRenderer::render( $this->phone->fresh(), '' );

        expect( $html )->toContain( 'wire:model.live="formData.phone_country"' )
            ->toContain( 'aria-label="Phone country"' )
            ->toContain( 'autocomplete="country"' )
            ->toContain( '<option value="GB">United Kingdom (+44)</option>' );
    } );

    it( 'renders no selector for a US field', function (): void {
        expect( FieldRenderer::render( $this->phone, '' ) )->not->toContain( 'phone_country' );
    } );

    it( 'sends the format and countries to the React and Vue renderers', function (): void {
        $this->phone->update( ['field_config' => ['phone_format' => 'international', 'default_country' => 'GB']] );
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['phone']['format'] )->toBe( 'international' )
            ->and( $fields[0]['phone']['default_country'] )->toBe( 'GB' )
            ->and( collect( $fields[0]['phone']['countries'] )->firstWhere( 'value', 'GB' )['dial_code'] )->toBe( '44' );
    } );

    it( 'sends no countries for a US field and nothing for any format', function (): void {
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['phone'] )->toBe( ['format' => 'us', 'default_country' => 'US', 'countries' => []] );

        $this->phone->update( ['field_config' => []] );
        $fields = ( new FormRenderResource( $this->form->fresh()->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0] )->not->toHaveKey( 'phone' );
    } );
} );
