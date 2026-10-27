<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\NumberFormat;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #118: number formats, whole numbers only, and prefix / suffix.
 */
beforeEach( function (): void {
    $this->form   = Form::factory()->create( ['is_active' => true] );
    $this->amount = FormField::factory()->for( $this->form )->create( [
        'type'         => 'number',
        'name'         => 'amount',
        'label'        => 'Amount',
        'field_config' => ['number_format' => 'decimal_dot'],
    ] );
} );

describe( 'NumberFormat', function (): void {
    it( 'converts formatted numbers to plain decimals', function ( string $format, mixed $value, mixed $expected ): void {
        expect( NumberFormat::normalize( $value, $format ) )->toBe( $expected );
    } )->with( [
        'dot grouping'        => ['decimal_dot', '1,234.56', '1234.56'],
        'dot spaces'          => ['decimal_dot', ' 1 234.5 ', '1234.5'],
        'dot negative'        => ['decimal_dot', '-1,000', '-1000'],
        'dot symbols'         => ['decimal_dot', '$1,200 USD', '1200'],
        'dot trailing point'  => ['decimal_dot', '12.', '12'],
        'comma grouping'      => ['decimal_comma', '1.234,56', '1234.56'],
        'comma plain'         => ['decimal_comma', '7,5', '7.5'],
        'currency rounds'     => ['currency', '$1,234.567', '1234.57'],
        'currency pads'       => ['currency', '12', '12.00'],
        'currency number'     => ['currency', 3, '3.00'],
        'not a number'        => ['decimal_dot', 'abc', 'abc'],
        'two decimal points'  => ['decimal_dot', '1.2.3', '1.2.3'],
        'blank'               => ['decimal_dot', '', ''],
        'unknown format'      => ['other', '1,234', '1,234'],
    ] );
} );

describe( 'settings', function (): void {
    it( 'offers the number options', function (): void {
        expect( array_column( FieldSettings::schema( $this->amount ), 'key' ) )->toContain( 'number_format', 'integer_only', 'prefix', 'suffix' );
    } );

    it( 'stores whole numbers only as a validation rule', function (): void {
        expect( FieldSettings::find( $this->amount, 'integer_only' )['store'] )->toBe( 'validation_rules' );
    } );
} );

describe( 'validation and storage', function (): void {
    it( 'validates and stores a formatted number as a plain decimal', function (): void {
        $this->amount->update( ['validation_rules' => ['min' => 1000]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.amount', '999.99' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.amount' => 'min'] )
            ->set( 'formData.amount', '1,234.50' )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'formData.amount', '1,234.50' );

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '1234.50' );
    } );

    it( 'stores a comma-decimal number through the API', function (): void {
        $this->amount->update( ['field_config' => ['number_format' => 'decimal_comma']] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['amount' => '1.234,5'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '1234.5' );
    } );

    it( 'rejects a value that does not read as a number', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['amount' => '12abc34x5,6.7.8'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.amount' );
    } );

    it( 'adds the whole-number rule for whole numbers only', function (): void {
        $this->amount->update( ['field_config' => [], 'validation_rules' => ['integer_only' => true]] );
        $field = $this->amount->fresh();

        expect( $field->buildValidationRules() )->toContain( 'numeric', 'multiple_of:1' )
            ->and( $field->buildValidationMessages()['multiple_of'] )->toBe( 'Please enter a whole number for Amount.' );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.amount', '2.5' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.amount' => 'multiple_of'] )
            ->set( 'formData.amount', '3' )
            ->call( 'submit' )
            ->assertHasNoErrors();
    } );

    it( 'accepts a whole currency amount, which is stored with two decimals', function (): void {
        $this->amount->update( ['field_config' => ['number_format' => 'currency'], 'validation_rules' => ['integer_only' => true]] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['amount' => '$1,200.5'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.amount' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['amount' => '$1,200'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '1200.00' );
    } );

    it( 'leaves a plain number field unchanged', function (): void {
        $this->amount->update( ['field_config' => []] );

        expect( $this->amount->fresh()->buildValidationRules() )->not->toContain( 'multiple_of:1' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['amount' => '1,234'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.amount' );
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders a formatted number as a text input with a decimal keypad', function (): void {
        $html = FieldRenderer::render( $this->amount, '' );

        expect( $html )->toContain( 'type="text"' )->toContain( 'inputmode="decimal"' )->not->toContain( 'step=' );
    } );

    it( 'renders a plain number input with step 1 for whole numbers', function (): void {
        $this->amount->update( ['field_config' => [], 'validation_rules' => ['integer_only' => true]] );

        expect( FieldRenderer::render( $this->amount->fresh(), '' ) )->toContain( 'type="number"' )->toContain( 'step="1"' );
    } );

    it( 'shows the prefix and suffix and describes them for screen readers', function (): void {
        $this->amount->update( ['help_text' => null, 'field_config' => ['prefix' => '$', 'suffix' => 'USD']] );
        $field = $this->amount->fresh();
        $html  = FieldRenderer::render( $field, '' );

        expect( $field->getAffixes() )->toBe( ['prefix' => '$', 'suffix' => 'USD'] )
            ->and( $html )->toContain( 'id="affix-amount"' )
            ->toContain( 'Prefix: $. Suffix: USD.' )
            ->toContain( '<span class="label">$</span>' )
            ->toContain( '<span class="label">USD</span>' )
            ->toContain( 'aria-describedby="affix-amount"' );
    } );

    it( 'describes a prefix or suffix on its own', function (): void {
        expect( FormField::factory()->make( ['type' => 'number', 'name' => 'n', 'field_config' => ['suffix' => 'kg']] )->getAffixDescription() )->toBe( 'Suffix: kg.' )
            ->and( FormField::factory()->make( ['type' => 'number', 'name' => 'n', 'field_config' => ['prefix' => '€']] )->getAffixDescription() )->toBe( 'Prefix: €.' )
            ->and( FormField::factory()->make( ['type' => 'number', 'name' => 'n'] )->getAffixDescription() )->toBeNull();
    } );

    it( 'sends the affix description to the React and Vue renderers', function (): void {
        $this->amount->update( ['field_config' => ['suffix' => 'kg']] );
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['affix_description'] )->toBe( 'Suffix: kg.' );
    } );
} );
