<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FieldTypes\AddressFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\PopulationContext;
use ArtisanPackUI\Forms\Support\PredefinedChoices;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #110: the Address field (street, city, state, postal code
 * and country; international, U.S. and Canada types; the shared
 * `ap.forms.countries` filter).
 */
beforeEach( function (): void {
    $this->form    = Form::factory()->create( ['is_active' => true] );
    $this->address = FormField::factory()->for( $this->form )->required()->create( [
        'type'  => 'address',
        'name'  => 'address',
        'label' => 'Mailing address',
    ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.forms.countries' );
    removeAllFilters( 'ap.forms.addressAutocomplete' );
} );

/**
 * Stores a submission holding an address value.
 *
 * @param  array<string, string>  $parts  The address parts.
 */
function storeAddress( Form $form, array $parts ): FormSubmission
{
    return app( SubmissionService::class )->create( $form, ['address' => $parts] );
}

describe( 'sub-inputs', function (): void {
    it( 'offers the six address parts with their autocomplete tokens', function (): void {
        $subInputs = $this->address->getSubInputs();

        expect( array_column( $subInputs, 'autocomplete', 'key' ) )->toBe( [
            'street'      => 'address-line1',
            'street2'     => 'address-line2',
            'city'        => 'address-level2',
            'state'       => 'address-level1',
            'postal_code' => 'postal-code',
            'country'     => 'country-name',
        ] )
            ->and( $subInputs['state']['type'] )->toBe( 'text' )
            ->and( $subInputs['country']['type'] )->toBe( 'select' )
            ->and( $subInputs['street']['wide'] )->toBeTrue()
            ->and( $this->address->getRequiredSubInputKeys() )->toBe( ['street', 'city', 'postal_code', 'country'] );
    } );

    it( 'lets each part be toggled, relabelled and required', function (): void {
        $this->address->update( ['field_config' => ['sub_inputs' => [
            'street2' => ['enabled' => false],
            'state'   => ['label' => 'County', 'required' => true],
        ]]] );
        $field = $this->address->fresh();

        expect( array_keys( $field->getEnabledSubInputs() ) )->not->toContain( 'street2' )
            ->and( $field->getSubInputs()['state']['label'] )->toBe( 'County' )
            ->and( $field->getRequiredSubInputKeys() )->toContain( 'state' );
    } );

    it( 'lists the countries from the shared ap.forms.countries filter', function (): void {
        addFilter( 'ap.forms.countries', function ( array $countries ): array {
            unset( $countries['US'] );
            $countries['XK'] = 'Kosovo';

            return $countries;
        } );

        $values = array_column( $this->address->getSubInputs()['country']['options'], 'value' );

        expect( $values )->toContain( 'XK' )->not->toContain( 'US' )
            ->and( PredefinedChoices::get( 'countries' ) )->not->toBeNull();
    } );

    it( 'ignores a countries filter that returns something other than an array', function (): void {
        addFilter( 'ap.forms.countries', fn (): string => 'nope' );

        expect( PredefinedChoices::countries() )->toHaveKey( 'US' );
    } );
} );

describe( 'address types', function (): void {
    it( 'uses a state select, ZIP code and fixed country for the United States', function (): void {
        $this->address->update( ['field_config' => ['address_type' => 'us']] );
        $subInputs = $this->address->fresh()->getSubInputs();

        expect( $subInputs )->not->toHaveKey( 'country' )
            ->and( $subInputs['state'] )->toMatchArray( ['label' => 'State', 'type' => 'select'] )
            ->and( array_column( $subInputs['state']['options'], 'value' ) )->toContain( 'NY', 'CA', 'DC' )
            ->and( $subInputs['postal_code']['label'] )->toBe( 'ZIP Code' );
    } );

    it( 'checks a U.S. ZIP code and stores the fixed country', function (): void {
        $this->address->update( ['field_config' => ['address_type' => 'us']] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.address', ['street' => '1 Main St', 'city' => 'Albany', 'state' => 'NY', 'postal_code' => '1220'] )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.address.postal_code' => 'regex'] )
            ->assertSee( 'Mailing address: enter a ZIP code such as 12345 or 12345-6789.' )
            ->set( 'formData.address.postal_code', '12207-1234' )
            ->set( 'formData.address.state', 'ZZ' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.address.state' => 'in'] )
            ->set( 'formData.address.state', 'NY' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value_array )->toMatchArray( ['postal_code' => '12207-1234', 'country' => 'US'] )
            ->and( $value->display_value )->toBe( "1 Main St\nAlbany, NY 12207-1234\nUnited States" );
    } );

    it( 'checks a Canadian postal code and stores it in upper case', function (): void {
        $this->address->update( ['field_config' => ['address_type' => 'canada']] );
        $subInputs = $this->address->fresh()->getSubInputs();

        expect( $subInputs['state']['label'] )->toBe( 'Province' )
            ->and( array_column( $subInputs['state']['options'], 'value' ) )->toContain( 'ON', 'QC' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['address' => ['street' => '24 Sussex Dr', 'city' => 'Ottawa', 'state' => 'ON', 'postal_code' => '12345']],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.address.postal_code' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['address' => ['street' => '24 Sussex Dr', 'city' => 'Ottawa', 'state' => 'ON', 'postal_code' => 'k1m 1m4']],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values()->first()->value_array )->toMatchArray( ['postal_code' => 'K1M 1M4', 'country' => 'CA'] );
    } );

    it( 'rejects a country that is not in the list', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['address' => ['street' => '1 Rue', 'city' => 'Paris', 'postal_code' => '75001', 'country' => 'Atlantis']],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.address.country' );
    } );
} );

describe( 'default and hidden country', function (): void {
    it( 'starts with the default country and state', function (): void {
        $this->address->update( ['field_config' => ['default_country' => 'FR', 'default_state' => 'Île-de-France']] );

        expect( DynamicPopulation::initialValue( $this->address->fresh(), PopulationContext::trusted() ) )
            ->toMatchArray( ['state' => 'Île-de-France', 'country' => 'FR'] );
    } );

    it( 'ignores a default country that is not in the list', function (): void {
        $this->address->update( ['field_config' => ['default_country' => 'ZZ']] );

        expect( AddressFieldType::defaultCountry( $this->address->fresh() ) )->toBeNull();
    } );

    it( 'hides the country and stores the default when set', function (): void {
        $this->address->update( ['field_config' => ['default_country' => 'GB', 'hide_country' => true]] );
        $field = $this->address->fresh();

        expect( $field->getSubInputs() )->not->toHaveKey( 'country' );

        storeAddress( $this->form, ['street' => '10 Downing St', 'city' => 'London', 'postal_code' => 'SW1A 2AA', 'country' => 'FR'] );

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value_array['country'] )->toBe( 'GB' )
            ->and( $value->display_value )->toEndWith( 'United Kingdom' );
    } );

    it( 'keeps the country input when hidden without a default', function (): void {
        $this->address->update( ['field_config' => ['hide_country' => true]] );

        expect( $this->address->fresh()->getSubInputs() )->toHaveKey( 'country' );
    } );
} );

describe( 'display', function (): void {
    it( 'shows a multi-line address with the country name', function (): void {
        $submission = storeAddress( $this->form, ['street' => '1 Rue de Rivoli', 'street2' => 'Apt 4', 'city' => 'Paris', 'state' => '', 'postal_code' => '75001', 'country' => 'FR'] );

        expect( $submission->fresh()->values->first()->display_value )->toBe( "1 Rue de Rivoli\nApt 4\nParis, 75001\nFrance" );
    } );

    it( 'keeps the line breaks in the notification table', function (): void {
        $submission = storeAddress( $this->form, ['street' => '1 Rue de Rivoli', 'city' => 'Paris', 'postal_code' => '75001', 'country' => 'FR'] );

        expect( app( NotificationService::class )->formatAllFieldsAsTable( $submission->fresh() ) )->toContain( '1 Rue de Rivoli<br>' );
    } );

    it( 'offers a merge tag per part', function (): void {
        expect( array_keys( $this->address->fieldType()->mergeTags( $this->address ) ) )->toContain( '{address}', '{address:city}', '{address:postal_code}', '{address:country}' );
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders a wide street input and a country select', function (): void {
        $html = FieldRenderer::render( $this->address, [] );

        expect( $html )->toContain( 'data-forms-composite="address"' )
            ->toContain( 'sm:col-span-2' )
            ->toContain( 'autocomplete="country-name"' )
            ->toContain( '<option value="FR">France</option>' );
    } );

    it( 'renders the address autocomplete hook after the fieldset', function (): void {
        addFilter( 'ap.forms.addressAutocomplete', fn ( string $html, FormField $field ): string => '<script data-provider="test" data-field="' . $field->name . '"></script>' );

        expect( FieldRenderer::render( $this->address, [] ) )->toContain( '<script data-provider="test" data-field="address"></script>' );
    } );

    it( 'sends the parts to the React and Vue renderers', function (): void {
        $this->address->update( ['field_config' => ['address_type' => 'us']] );
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( array_column( $fields[0]['sub_inputs'], 'key' ) )->toBe( ['street', 'street2', 'city', 'state', 'postal_code'] )
            ->and( $fields[0]['sub_inputs'][3]['options'] )->toContain( ['value' => 'NY', 'label' => 'New York'] );
    } );
} );
