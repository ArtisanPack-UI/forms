<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\CalculationFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\Calculation;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #135: the calculation field.
 */
beforeEach( function (): void {
    $this->form     = Form::factory()->create( ['is_active' => true] );
    $this->quantity = FormField::factory()->for( $this->form )->create( ['type' => 'number', 'name' => 'quantity', 'label' => 'Quantity', 'sort_order' => 1] );
    $this->price    = FormField::factory()->for( $this->form )->create( ['type' => 'number', 'name' => 'price', 'label' => 'Price', 'sort_order' => 2] );
    $this->total    = FormField::factory()->for( $this->form )->create( [
        'type'         => 'calculation',
        'name'         => 'total',
        'label'        => 'Total',
        'help_text'    => null,
        'sort_order'   => 3,
        'field_config' => ['formula' => '{quantity} * {price}', 'decimals' => 2],
    ] );

    $this->submitApi = fn ( array $data ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => $data,
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'registration', function (): void {
    it( 'is an advanced field with formula and decimal places settings', function (): void {
        $config = FieldTypes::getTypeConfig( 'calculation' );

        expect( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'calculation' )
            ->and( $config )->toMatchArray( [
                'label'                       => 'Calculation',
                'icon'                        => 'o-calculator',
                'supports_default_value'      => false,
                'supports_dynamic_population' => false,
                'defaults'                    => ['label' => 'Total', 'field_config' => ['formula' => '', 'decimals' => 2]],
            ] )
            ->and( array_slice( array_column( $config['settings'], 'key' ), 0, 2 ) )->toBe( ['formula', 'decimals'] )
            ->and( FieldSettings::find( $this->total, 'formula' ) )->toMatchArray( ['input' => 'formula', 'max_length' => Calculation::MAX_LENGTH] );
    } );

    it( 'can be added from the Livewire builder palette', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )->call( 'addField', 'calculation' );

        expect( $this->form->fields()->where( 'type', 'calculation' )->whereKeyNot( $this->total->id )->sole()->field_config )->toBe( ['formula' => '', 'decimals' => 2] );
    } );

    it( 'supports the numeric conditional logic operators', function (): void {
        expect( array_keys( ConditionalLogic::getOperatorsForType( 'calculation' ) ) )
            ->toContain( 'equals', 'greater_than', 'less_or_equal', 'in', 'is_empty' );
    } );

    it( 'clamps the decimal places', function ( mixed $decimals, int $expected ): void {
        $this->total->update( ['field_config' => ['formula' => '1', 'decimals' => $decimals]] );

        expect( CalculationFieldType::decimals( $this->total->fresh() ) )->toBe( $expected );
    } )->with( [
        'set'      => [0, 0],
        'too many' => [25, 10],
        'negative' => [-1, 0],
        'unset'    => [null, 2],
        'text'     => ['two', 2],
    ] );
} );

describe( 'parser', function (): void {
    beforeEach( function (): void {
        $this->values  = ['quantity' => '3', 'price' => '19.99', 'tags' => ['1', '2.5'], 'blank' => '', 'text' => 'abc', 'on' => true];
        $this->resolve = fn ( string $name ): mixed => $this->values[ $name ] ?? null;
    } );

    it( 'evaluates formulas with the usual precedence', function ( string $formula, ?float $expected ): void {
        expect( Calculation::evaluate( $formula, $this->resolve ) )->toBe( $expected );
    } )->with( [
        'product'            => ['{quantity} * {price}', 59.97],
        'precedence'         => ['2 + 3 * 4', 14.0],
        'parentheses'        => ['(2 + 3) * 4', 20.0],
        'unary minus'        => ['-{quantity} + 10', 7.0],
        'double negation'    => ['- - 3', 3.0],
        'decimals'           => ['.5 + 1.25', 1.75],
        'list sum'           => ['{tags} * 2', 7.0],
        'blank, text, true'  => ['{blank} + {text} + {on} + {missing}', 1.0],
        'division by zero'   => ['1 / ({quantity} - 3)', null],
        'trailing operator'  => ['2 *', null],
        'unclosed'           => ['(1 + 2', null],
        'unopened'           => ['1 + 2)', null],
        'two numbers'        => ['1 2', null],
        'power'              => ['2 ** 3', null],
        'empty'              => ['', null],
    ] );

    it( 'never runs anything but arithmetic', function ( string $formula ): void {
        expect( Calculation::tokenize( $formula ) )->toBeNull()
            ->and( Calculation::evaluate( $formula, $this->resolve ) )->toBeNull();
    } )->with( [
        'function call'  => ['phpinfo()'],
        'variable'       => ['$quantity * 2'],
        'backticks'      => ['`id`'],
        'exponent'       => ['1e3'],
        'merge tag code' => ['{quantity;exit}'],
    ] );

    it( 'limits the formula length and nesting', function (): void {
        expect( Calculation::tokenize( str_repeat( '1+', 600 ) . '1' ) )->toBeNull()
            ->and( Calculation::evaluate( str_repeat( '(', 40 ) . '1' . str_repeat( ')', 40 ), $this->resolve ) )->toBeNull()
            ->and( Calculation::evaluate( str_repeat( '(', 10 ) . '1' . str_repeat( ')', 10 ), $this->resolve ) )->toBe( 1.0 );
    } );

    it( 'lists the fields a formula refers to', function (): void {
        expect( Calculation::references( '{quantity} * {price} + {quantity}' ) )->toBe( ['quantity', 'price'] );
    } );

    it( 'explains the first problem with a formula', function ( string $formula, ?string $expected ): void {
        expect( Calculation::check( $formula, ['quantity', 'price'] ) )->toBe( $expected );
    } )->with( [
        'valid'           => ['{quantity} * {price}', null],
        'divides by zero' => ['{quantity} / 0', null],
        'blank'           => ['  ', 'Enter a formula, such as {quantity} * {price}.'],
        'bad character'   => ['{quantity} ^ 2', 'The formula may only use numbers, field merge tags such as {price}, + - * / and parentheses.'],
        'unknown field'   => ['{quantity} * {tax}', 'The formula refers to {tax}, which is not a field on this form.'],
        'incomplete'      => ['{quantity} *', 'The formula is incomplete or has an operator or parenthesis out of place.'],
    ] );

    it( 'formats results with fixed decimal places', function (): void {
        expect( Calculation::format( 59.97, 2 ) )->toBe( '59.97' )
            ->and( Calculation::format( 2.5, 0 ) )->toBe( '3' )
            ->and( Calculation::format( -2.5, 0 ) )->toBe( '-3' )
            ->and( Calculation::format( 10 / 3, 4 ) )->toBe( '3.3333' )
            ->and( Calculation::format( -0.001, 2 ) )->toBe( '0.00' )
            ->and( Calculation::format( null, 2 ) )->toBe( '' );
    } );
} );

describe( 'submission', function (): void {
    it( 'works the result out on the server and ignores the client value', function (): void {
        ( $this->submitApi )( ['quantity' => '3', 'price' => '19.99', 'total' => '0.01'] )->assertCreated();

        expect( $this->form->submissions()->first()->values()->where( 'field_name', 'total' )->value( 'value' ) )->toBe( '59.97' );
    } );

    it( 'stores no result when the formula has none', function (): void {
        ( $this->submitApi )( ['quantity' => '3', 'price' => '0', 'total' => 'anything'] )->assertCreated();

        $this->total->update( ['field_config' => ['formula' => '{quantity} / {price}']] );
        ( $this->submitApi )( ['quantity' => '3', 'price' => '0', 'total' => '999'] )->assertCreated();

        expect( $this->form->submissions()->latest( 'id' )->first()->values()->where( 'field_name', 'total' )->value( 'value' ) )->toBe( '' );
    } );

    it( 'rejects nothing the client sends for a calculation', function (): void {
        ( $this->submitApi )( ['quantity' => '2', 'price' => '5', 'total' => ['not', 'a', 'number']] )->assertCreated();

        expect( $this->form->submissions()->first()->values()->where( 'field_name', 'total' )->value( 'value' ) )->toBe( '10.00' );
    } );

    it( 'uses other calculations and gives a cycle no result', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'calculation',
            'name'         => 'with_tax',
            'sort_order'   => 0,
            'field_config' => ['formula' => '{total} * 1.1', 'decimals' => 2],
        ] );
        $fields = $this->form->fields()->get();

        $results = app( SubmissionService::class )->applyCalculations( $fields, ['quantity' => '2', 'price' => '10'] );

        expect( $results['total'] )->toBe( '20.00' )
            ->and( $results['with_tax'] )->toBe( '22.00' );

        $this->total->update( ['field_config' => ['formula' => '{with_tax} + 1']] );

        $results = app( SubmissionService::class )->applyCalculations( $this->form->fields()->get(), ['quantity' => '2', 'price' => '10'] );

        expect( $results['total'] )->toBe( '' )
            ->and( $results['with_tax'] )->toBe( '' );
    } );

    it( 'reads numbers typed in a number field\'s format', function (): void {
        $this->price->update( ['field_config' => ['number_format' => 'decimal_comma']] );

        $results = app( SubmissionService::class )->applyCalculations( $this->form->fields()->get(), ['quantity' => '2', 'price' => '1.234,50'] );

        expect( $results['total'] )->toBe( '2469.00' );
    } );

    it( 'leaves out a calculation conditional logic hides', function (): void {
        $this->total->update( ['conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'quantity', 'operator' => 'greater_than', 'value' => '5']]]] );

        ( $this->submitApi )( ['quantity' => '3', 'price' => '2', 'total' => '6.00'] )->assertCreated();

        expect( $this->form->submissions()->first()->values()->where( 'field_name', 'total' )->exists() )->toBeFalse();
    } );

    it( 'reads an administrative field as blank, whatever the client sent', function (): void {
        $this->price->update( ['field_config' => ['visibility' => 'administrative']] );

        expect( $this->price->fresh()->isAdministrative() )->toBeTrue()
            ->and( CalculationFieldType::formulaFields( $this->total, $this->form->fields()->get() ) )->not->toContain( ['name' => 'price', 'label' => 'Price'] );

        ( $this->submitApi )( ['quantity' => '3', 'price' => '1000', 'total' => '3000.00'] )->assertCreated();

        expect( $this->form->submissions()->first()->values()->where( 'field_name', 'total' )->value( 'value' ) )->toBe( '0.00' );
    } );

    it( 'decides which fields are shown from the server\'s result, not the client\'s', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'type'              => 'text',
            'name'              => 'bulk_notes',
            'sort_order'        => 4,
            'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'total', 'operator' => 'greater_than', 'value' => '100']]],
        ] );

        ( $this->submitApi )( ['quantity' => '1', 'price' => '2', 'total' => '500.00', 'bulk_notes' => 'Spoofed'] )->assertCreated();

        $values = $this->form->submissions()->first()->values();

        expect( $values->where( 'field_name', 'total' )->value( 'value' ) )->toBe( '2.00' )
            ->and( $values->where( 'field_name', 'bulk_notes' )->exists() )->toBeFalse();
    } );
} );

describe( 'Livewire renderer', function (): void {
    it( 'shows the result as the values change and stores it', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->assertSet( 'formData.total', '0.00' )
            ->set( 'formData.quantity', '4' )
            ->set( 'formData.price', '2.5' )
            ->assertSet( 'formData.total', '10.00' )
            ->assertSeeHtml( 'value="10.00"' )
            ->set( 'formData.total', '1.00' )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values()->where( 'field_name', 'total' )->value( 'value' ) )->toBe( '10.00' );
    } );

    it( 'works a result out without the values of hidden fields', function (): void {
        $this->price->update( ['conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'quantity', 'operator' => 'greater_than', 'value' => '5']]]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formData.price', '2' )
            ->set( 'formData.quantity', '3' )
            ->assertSet( 'formData.total', '0.00' )
            ->set( 'formData.quantity', '6' )
            ->assertSet( 'formData.total', '12.00' );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders a labelled read-only input', function (): void {
        $html = FieldRenderer::render( $this->total, '59.97' );

        expect( $html )->toContain( 'id="field-total"' )
            ->toContain( 'for="field-total"' )
            ->toContain( 'value="59.97"' )
            ->toContain( 'readonly' )
            ->toContain( 'aria-readonly="true"' )
            ->not->toContain( 'wire:model' );
    } );

    it( 'sends the formula and decimal places to React and Vue', function (): void {
        $resource = ( new FormFieldResource( $this->total ) )->toArray( Request::create( '/' ) );

        expect( $resource['calculation'] )->toBe( ['formula' => '{quantity} * {price}', 'decimals' => 2] );
    } );
} );

describe( 'builder', function (): void {
    it( 'offers the other fields in the formula picker and flags problems', function (): void {
        $this->total->update( ['field_config' => ['formula' => '{quantity} * {tax}']] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->total->uuid )
            ->assertSeeHtml( 'id="field-setting-formula-insert"' )
            ->assertSeeHtml( '<option value="{quantity}">Quantity ({quantity})</option>' )
            ->assertSeeHtml( '<option value="{price}">Price ({price})</option>' )
            ->assertDontSeeHtml( '<option value="{total}">' )
            ->assertSee( 'The formula refers to {tax}, which is not a field on this form.' )
            ->call( 'updateSetting', 'formula', '{quantity} * {price}' )
            ->assertDontSee( 'which is not a field on this form' );

        expect( $this->total->fresh()->getConfig( 'formula' ) )->toBe( '{quantity} * {price}' );
    } );

    it( 'lists the fields a formula may use', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'heading', 'name' => 'intro'] );
        FormField::factory()->for( $this->form )->create( ['type' => 'file', 'name' => 'receipt'] );

        expect( array_column( CalculationFieldType::formulaFields( $this->total, $this->form->fields()->orderBy( 'sort_order' )->get() ), 'name' ) )
            ->toBe( ['quantity', 'price'] );
    } );
} );
