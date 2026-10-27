<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\RankingFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #133: the ranking field.
 */
beforeEach( function (): void {
    $this->form    = Form::factory()->create( ['is_active' => true] );
    $this->ranking = FormField::factory()->for( $this->form )->create( [
        'type'         => 'ranking',
        'name'         => 'priorities',
        'label'        => 'Priorities',
        'help_text'    => null,
        'field_config' => [
            'options' => [
                ['value' => 'price', 'label' => 'Price'],
                ['value' => 'quality', 'label' => 'Quality <b>'],
                ['value' => 'service', 'label' => 'Service'],
            ],
        ],
    ] );

    $this->submitApi = fn ( mixed $value ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['priorities' => $value],
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'registration', function (): void {
    it( 'is an advanced field with options and a randomize setting', function (): void {
        $config = FieldTypes::getTypeConfig( 'ranking' );

        expect( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'ranking' )
            ->and( FieldTypes::hasOptions( 'ranking' ) )->toBeTrue()
            ->and( $config )->toMatchArray( ['label' => 'Ranking', 'icon' => 'o-bars-arrow-down', 'supports_default_value' => false] )
            ->and( array_column( $config['settings'], 'key' ) )->toContain( 'randomize' )
            ->and( array_column( $config['defaults']['field_config']['options'], 'value' ) )->toBe( ['price', 'quality', 'service'] );
    } );
} );

describe( 'order', function (): void {
    it( 'starts with every choice in order', function (): void {
        expect( RankingFieldType::initialValue( $this->ranking ) )->toBe( ['price', 'quality', 'service'] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )->assertSet( 'formData.priorities', ['price', 'quality', 'service'] );
    } );

    it( 'fits an order to the choices', function (): void {
        expect( RankingFieldType::order( $this->ranking, ['service', 'gone', 'service', 'price'] ) )->toBe( ['service', 'price', 'quality'] )
            ->and( RankingFieldType::order( $this->ranking, 'text' ) )->toBe( ['price', 'quality', 'service'] );
    } );

    it( 'moves a choice, leaving out-of-range moves alone', function (): void {
        $order = ['a', 'b', 'c'];

        expect( RankingFieldType::move( $order, 2, 0 ) )->toBe( ['c', 'a', 'b'] )
            ->and( RankingFieldType::move( $order, 0, 1 ) )->toBe( ['b', 'a', 'c'] )
            ->and( RankingFieldType::move( $order, 0, 3 ) )->toBe( $order )
            ->and( RankingFieldType::move( $order, -1, 0 ) )->toBe( $order );
    } );
} );

describe( 'Livewire', function (): void {
    it( 'moves choices and announces where they went', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->call( 'moveRankingItem', 'priorities', 2, 0 )
            ->assertSet( 'formData.priorities', ['service', 'price', 'quality'] )
            ->assertDispatched( 'forms-ranking-moved', field: 'priorities', value: 'service', position: 0 )
            ->call( 'moveRankingItem', 'priorities', 0, -1 )
            ->assertSet( 'formData.priorities', ['service', 'price', 'quality'] );
    } );

    it( 'ignores moves on fields that are not rankings', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'title'] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->call( 'moveRankingItem', 'title', 0, 1 )
            ->assertNotDispatched( 'forms-ranking-moved' );
    } );

    it( 'stores the order', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->call( 'moveRankingItem', 'priorities', 1, 0 )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value_array )->toBe( ['quality', 'price', 'service'] );
    } );
} );

describe( 'validation', function (): void {
    it( 'stores a complete order from the API', function (): void {
        ( $this->submitApi )( ['service', 'price', 'quality'] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value_array )->toBe( ['service', 'price', 'quality'] );
    } );

    it( 'rejects missing, repeated or unknown choices', function ( array $order ): void {
        ( $this->submitApi )( $order )->assertUnprocessable()->assertJsonValidationErrors( ['data.priorities' => 'Please put every choice of Priorities in order.'] );
    } )->with( [
        'missing'  => [['price', 'quality']],
        'repeated' => [['price', 'price', 'quality']],
        'unknown'  => [['price', 'quality', 'speed']],
    ] );

    it( 'requires an order when required', function (): void {
        $this->ranking->update( ['is_required' => true] );

        ( $this->submitApi )( [] )->assertUnprocessable()->assertJsonValidationErrors( ['data.priorities' => 'Please rank the choices of Priorities.'] );
    } );
} );

describe( 'display and export', function (): void {
    beforeEach( function (): void {
        ( $this->submitApi )( ['quality', 'service', 'price'] )->assertCreated();

        $this->submission = $this->form->submissions()->first();
        $this->value      = $this->submission->values->first();
    } );

    it( 'shows the choices numbered in order, escaped in HTML', function (): void {
        expect( $this->value->display_value )->toBe( '1. Quality <b>, 2. Service, 3. Price' )
            ->and( $this->value->display_html )->toBe( '<ol style="margin: 0; padding-left: 1.5em; list-style: decimal;"><li>Quality &lt;b&gt;</li><li>Service</li><li>Price</li></ol>' )
            ->and( app( NotificationService::class )->formatAllFieldsAsTable( $this->submission->fresh( ['values'] ) ) )->toContain( '<li>Quality &lt;b&gt;</li>' );
    } );

    it( 'exports the numbered order', function (): void {
        $row = app( ExportService::class )->buildRow( $this->form, $this->submission->fresh( ['values'] ), $this->form->fields()->get() );

        expect( $row )->toContain( '1. Quality <b>, 2. Service, 3. Price' );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders an ordered list with labelled move buttons and a live region', function (): void {
        $html = FieldRenderer::render( $this->ranking, ['service', 'price', 'quality'] );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( '<ol' )
            ->toContain( 'draggable="true"' )
            ->toContain( 'aria-label="Move Service up"' )
            ->toContain( 'aria-label="Move Quality &lt;b&gt; down"' )
            ->toContain( 'id="field-priorities-0-up"' )
            ->toContain( 'aria-live="polite"' )
            ->toContain( 'id="field-priorities-instructions"' )
            ->toMatch( '/id="field-priorities-0-up"[^>]*disabled/s' )
            ->toMatch( '/id="field-priorities-2-down"[^>]*disabled/s' );
    } );

    it( 'sends the options and starting order to React and Vue', function (): void {
        $resource = ( new FormFieldResource( $this->ranking ) )->withResolvedDefault()->toArray( Request::create( '/' ) );

        expect( array_column( $resource['options'], 'value' ) )->toBe( ['price', 'quality', 'service'] )
            ->and( $resource['resolved_default_value'] )->toBe( ['price', 'quality', 'service'] );
    } );
} );
