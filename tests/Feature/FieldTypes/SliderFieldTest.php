<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\SliderFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #130: the slider (range) field.
 */
beforeEach( function (): void {
    $this->form   = Form::factory()->create( ['is_active' => true] );
    $this->slider = FormField::factory()->for( $this->form )->create( [
        'type'         => 'slider',
        'name'         => 'budget',
        'label'        => 'Budget',
        'help_text'    => null,
        'field_config' => ['min' => 10, 'max' => 50, 'step' => 5, 'prefix' => '$'],
    ] );

    $this->submitApi = fn ( mixed $value ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['budget' => $value],
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'registration', function (): void {
    it( 'is an advanced field with min, max, step and value label settings', function (): void {
        $config = FieldTypes::getTypeConfig( 'slider' );

        expect( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'slider' )
            ->and( $config )->toMatchArray( [
                'label'    => 'Slider',
                'icon'     => 'o-adjustments-horizontal',
                'defaults' => ['label' => 'Slider', 'field_config' => ['min' => 0, 'max' => 100, 'step' => 1]],
            ] )
            ->and( array_slice( array_column( $config['settings'], 'key' ), 0, 7 ) )->toBe( ['min', 'max', 'step', 'hide_value', 'prefix', 'suffix', 'read_only'] );
    } );

    it( 'can be added from the Livewire builder palette', function (): void {
        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )->call( 'addField', 'slider' );

        expect( $this->form->fields()->where( 'name', 'slider' )->sole()->field_config )->toBe( ['min' => 0, 'max' => 100, 'step' => 1] );
    } );

    it( 'supports the numeric conditional logic operators', function (): void {
        expect( array_keys( ConditionalLogic::getOperatorsForType( 'slider' ) ) )
            ->toContain( 'equals', 'greater_than', 'less_or_equal', 'in', 'is_empty' )
            ->not->toContain( 'contains' );
    } );
} );

describe( 'limits', function (): void {
    it( 'reads the limits and falls back to 0, 100 and 1', function (): void {
        expect( SliderFieldType::limits( $this->slider ) )->toBe( ['min' => 10, 'max' => 50, 'step' => 5] )
            ->and( SliderFieldType::limits( FormField::factory()->make( ['type' => 'slider', 'field_config' => []] ) ) )->toBe( ['min' => 0, 'max' => 100, 'step' => 1] )
            ->and( SliderFieldType::limits( FormField::factory()->make( ['type' => 'slider', 'field_config' => ['min' => '0.5', 'max' => '0.1', 'step' => '-2']] ) ) )->toBe( ['min' => 0.5, 'max' => 1.5, 'step' => 1] );
    } );

    it( 'starts at the default within the limits, or at the minimum', function ( mixed $default, string $expected ): void {
        expect( SliderFieldType::initialValue( $this->slider, $default ) )->toBe( $expected );
    } )->with( [
        'default'  => ['30', '30'],
        'none'     => [null, '10'],
        'too high' => ['90', '10'],
        'text'     => ['lots', '10'],
    ] );

    it( 'starts the Livewire renderer at the minimum', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )->assertSet( 'formData.budget', '10' );
    } );

    it( 'formats the value label', function (): void {
        expect( SliderFieldType::valueText( $this->slider, '25' ) )->toBe( '$25' );

        $this->slider->update( ['field_config' => ['prefix' => 'USD', 'suffix' => 'per month']] );

        expect( SliderFieldType::valueText( $this->slider->fresh(), '25' ) )->toBe( 'USD 25 per month' );

        $this->slider->update( ['field_config' => ['suffix' => '%']] );

        expect( SliderFieldType::valueText( $this->slider->fresh(), '25' ) )->toBe( '25%' );
    } );
} );

describe( 'validation', function (): void {
    it( 'checks the number, the limits and the step from the minimum', function (): void {
        $rules = $this->slider->buildValidationRules();

        expect( $rules )->toBe( ['nullable', 'numeric', 'between:10,50', SubmissionRules::STEP . ':10,5'] );

        foreach ( ['10' => true, '35' => true, '50' => true, '12' => false, '55' => false, '5' => false, 'abc' => false] as $value => $passes ) {
            expect( Validator::make( ['v' => (string) $value], ['v' => $rules] )->passes() )->toBe( $passes, "value {$value}" );
        }
    } );

    it( 'allows for floating point steps', function (): void {
        expect( SubmissionRules::isOnStep( '0.3', ['0', '0.1'] ) )->toBeTrue()
            ->and( SubmissionRules::isOnStep( '0.35', ['0', '0.1'] ) )->toBeFalse()
            ->and( SubmissionRules::isOnStep( '7', ['0', '0'] ) )->toBeTrue();
    } );

    it( 'names the limits and step in its messages', function (): void {
        ( $this->submitApi )( '60' )->assertUnprocessable()->assertJsonValidationErrors( ['data.budget' => 'The Budget must be between $10 and $50.'] );
        ( $this->submitApi )( '12' )->assertUnprocessable()->assertJsonValidationErrors( ['data.budget' => 'The Budget must be in steps of 5.'] );
    } );
} );

describe( 'storage, display and export', function (): void {
    it( 'stores the number, shows it with its prefix and exports the number', function (): void {
        ( $this->submitApi )( '35.0' )->assertCreated();

        $submission = $this->form->submissions()->first();
        $value      = $submission->values->first();
        $row        = app( ExportService::class )->buildRow( $this->form, $submission->fresh( ['values'] ), $this->form->fields()->get() );

        expect( $value->value )->toBe( '35' )
            ->and( $value->display_value )->toBe( '$35' )
            ->and( $row )->toContain( '35' )
            ->not->toContain( '$35' );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders a native range input with its value text', function (): void {
        $html = FieldRenderer::render( $this->slider, '25' );

        expect( $html )->toContain( 'type="range"' )
            ->toContain( 'min="10"' )
            ->toContain( 'max="50"' )
            ->toContain( 'step="5"' )
            ->toContain( 'value="25"' )
            ->toContain( 'aria-valuetext="$25"' )
            ->toContain( '<output' )
            ->toContain( 'for="field-budget"' );
    } );

    it( 'leaves out the value when hidden', function (): void {
        $this->slider->update( ['field_config' => array_merge( $this->slider->field_config, ['hide_value' => true] )] );

        expect( FieldRenderer::render( $this->slider->fresh(), '25' ) )->not->toContain( '<output' )
            ->toContain( 'aria-valuetext="$25"' );
    } );

    it( 'sends the limits and value label to React and Vue', function (): void {
        $resource = ( new FormFieldResource( $this->slider ) )->toArray( Request::create( '/' ) );

        expect( $resource['slider'] )->toBe( ['min' => 10, 'max' => 50, 'step' => 5, 'prefix' => '$', 'suffix' => '', 'show_value' => true] );
    } );
} );
