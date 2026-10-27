<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\ColorFieldType;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #136: the color picker field.
 */
beforeEach( function (): void {
    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->color = FormField::factory()->for( $this->form )->create( [
        'type'        => 'color',
        'name'        => 'brand_color',
        'label'       => 'Brand color',
        'placeholder' => null,
        'help_text'   => null,
    ] );

    $this->submitApi = fn ( mixed $value ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['brand_color' => $value],
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'registration', function (): void {
    it( 'is an advanced field with a read-only setting', function (): void {
        $config = FieldTypes::getTypeConfig( 'color' );

        expect( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'color' )
            ->and( $config )->toMatchArray( [
                'label'                => 'Color',
                'icon'                 => 'o-swatch',
                'supports_placeholder' => true,
                'defaults'             => ['label' => 'Color'],
            ] )
            ->and( array_column( $config['settings'], 'key' ) )->toContain( 'read_only' );
    } );

    it( 'can be added from the Livewire builder palette', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )->call( 'addField', 'color' );

        expect( $this->form->fields()->where( 'name', 'color' )->exists() )->toBeTrue();
    } );

    it( 'supports the equality and emptiness conditional logic operators', function (): void {
        expect( array_keys( ConditionalLogic::getOperatorsForType( 'color' ) ) )
            ->toContain( 'equals', 'not_equals', 'in', 'not_in', 'is_empty', 'is_not_empty' )
            ->not->toContain( 'greater_than' );
    } );
} );

describe( 'normalization', function (): void {
    it( 'normalizes a typed color', function ( mixed $value, mixed $expected ): void {
        expect( ColorFieldType::normalizeValue( $value ) )->toBe( $expected );
    } )->with( [
        'lowercase'      => ['#FF8800', '#ff8800'],
        'without a hash' => ['ff8800', '#ff8800'],
        'short form'     => ['#F80', '#ff8800'],
        'short alpha'    => ['f80c', '#ff8800cc'],
        'with alpha'     => [' #FF8800CC ', '#ff8800cc'],
        'blank'          => ['  ', ''],
        'not a color'    => [' orange ', 'orange'],
        'not a string'   => [null, null],
    ] );

    it( 'gives the color input a #rrggbb value', function (): void {
        expect( ColorFieldType::pickerValue( '#ff8800cc' ) )->toBe( '#ff8800' )
            ->and( ColorFieldType::pickerValue( 'F80' ) )->toBe( '#ff8800' )
            ->and( ColorFieldType::pickerValue( 'orange' ) )->toBe( '#000000' );
    } );
} );

describe( 'validation', function (): void {
    it( 'checks the value with hex_color', function (): void {
        $rules = $this->color->buildValidationRules();

        expect( $rules )->toBe( ['nullable', 'string', 'hex_color'] );

        foreach ( ['#ff8800' => true, '#f80' => true, '#ff8800cc' => true, 'ff8800' => false, '#ff880' => false, '#gg8800' => false, 'red' => false] as $value => $passes ) {
            expect( Validator::make( ['v' => (string) $value], ['v' => $rules] )->passes() )->toBe( $passes, "value {$value}" );
        }
    } );

    it( 'accepts a color typed without a hash or in uppercase', function (): void {
        ( $this->submitApi )( 'FF8800' )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '#ff8800' );
    } );

    it( 'rejects a value that is not a hex color', function (): void {
        ( $this->submitApi )( 'orange' )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( ['data.brand_color' => 'The Brand color must be a hex color, such as #ff8800.'] );
    } );

    it( 'requires a value when required', function (): void {
        $this->color->update( ['is_required' => true] );

        ( $this->submitApi )( '' )->assertUnprocessable()->assertJsonValidationErrors( 'data.brand_color' );
    } );

    it( 'normalizes and validates in the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.brand_color', '#F80' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '#ff8800' );
    } );
} );

describe( 'storage, display and export', function (): void {
    it( 'shows a swatch beside the hex code and exports the hex code', function (): void {
        ( $this->submitApi )( '#3366ff' )->assertCreated();

        $submission = $this->form->submissions()->first();
        $value      = $submission->values->first();
        $row        = app( ExportService::class )->buildRow( $this->form, $submission->fresh( ['values'] ), $this->form->fields()->get() );

        expect( $value->display_value )->toBe( '#3366ff' )
            ->and( $value->display_html )->toContain( 'aria-hidden="true"' )
            ->toContain( 'background-color: #3366ff;' )
            ->toContain( '<code>#3366ff</code>' )
            ->and( $row )->toContain( '#3366ff' );
    } );

    it( 'shows no swatch for a stored value that is not a color', function (): void {
        $submission = $this->form->submissions()->create( ['ip_address' => '127.0.0.1'] );
        $value      = $submission->values()->create( [
            'field_id'   => $this->color->id,
            'field_name' => 'brand_color',
            'field_type' => 'color',
            'value'      => '"><script>',
        ] );

        expect( $value->display_html )->toBeNull();
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders a labelled color picker beside a labelled hex input', function (): void {
        $html = FieldRenderer::render( $this->color, '#ff8800' );

        expect( $html )->toContain( 'type="color"' )
            ->toContain( 'id="field-brand_color-picker"' )
            ->toContain( 'aria-label="Brand color color picker"' )
            ->toContain( 'value="#ff8800"' )
            ->toContain( 'for="field-brand_color"' )
            ->toContain( 'id="field-brand_color"' )
            ->toContain( 'placeholder="#rrggbb"' );
    } );

    it( 'disables the picker and makes the hex input read-only for a read-only field', function (): void {
        $this->color->update( ['field_config' => ['read_only' => true], 'default_value' => '#112233'] );

        $html = FieldRenderer::render( $this->color->fresh(), '#112233' );

        expect( $html )->toContain( 'disabled' )
            ->toContain( 'readonly aria-readonly="true"' );
    } );

    it( 'marks the hex input invalid and describes it by the error', function (): void {
        $html = FieldRenderer::render( $this->color, 'orange', 'The Brand color must be a hex color.' );

        expect( $html )->toContain( 'aria-invalid="true"' )
            ->toContain( 'aria-describedby="error-brand_color"' )
            ->toContain( 'value="#000000"' );
    } );
} );
