<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\NpsFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #132: the Net Promoter Score field.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    $this->nps  = FormField::factory()->for( $this->form )->create( [
        'type'        => 'nps',
        'name'        => 'recommend',
        'label'       => 'Recommend',
        'help_text'   => null,
        'is_required' => true,
    ] );

    $this->submitApi = fn ( mixed $value ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['recommend' => $value],
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'registration', function (): void {
    it( 'is an advanced field with end label and category settings', function (): void {
        $config = FieldTypes::getTypeConfig( 'nps' );

        expect( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'nps' )
            ->and( $config )->toMatchArray( ['label' => 'Net Promoter Score', 'icon' => 'o-hand-thumb-up'] )
            ->and( array_slice( array_column( $config['settings'], 'key' ), 0, 4 ) )->toBe( ['low_label', 'high_label', 'show_category', 'read_only'] );
    } );

    it( 'supports the numeric conditional logic operators', function (): void {
        expect( array_keys( ConditionalLogic::getOperatorsForType( 'nps' ) ) )->toContain( 'equals', 'greater_or_equal', 'less_than', 'in' );
    } );

    it( 'defaults the end labels', function (): void {
        expect( NpsFieldType::endLabels( $this->nps ) )->toBe( ['low' => 'Not likely', 'high' => 'Extremely likely'] );

        $this->nps->update( ['field_config' => ['low_label' => ' Never ', 'high_label' => '']] );

        expect( NpsFieldType::endLabels( $this->nps->fresh() ) )->toBe( ['low' => 'Never', 'high' => 'Extremely likely'] );
    } );
} );

describe( 'categories', function (): void {
    it( 'tags scores as promoters, passives and detractors', function ( mixed $score, ?string $category ): void {
        expect( NpsFieldType::category( $score ) )->toBe( $category );
    } )->with( [
        [10, 'promoter'],
        ['9', 'promoter'],
        ['8', 'passive'],
        [7, 'passive'],
        ['6', 'detractor'],
        ['0', 'detractor'],
        ['11', null],
        ['7.5', null],
        ['', null],
    ] );
} );

describe( 'validation and storage', function (): void {
    it( 'accepts whole scores from 0 to 10, including 0', function (): void {
        ( $this->submitApi )( '0' )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '0' );
    } );

    it( 'rejects scores outside the scale', function ( string $score ): void {
        ( $this->submitApi )( $score )->assertUnprocessable()->assertJsonValidationErrors( ['data.recommend' => 'Please choose a score from 0 to 10 for Recommend.'] );
    } )->with( ['11', '-1', '4.5', 'ten'] );

    it( 'requires a score', function (): void {
        ( $this->submitApi )( '' )->assertUnprocessable()->assertJsonValidationErrors( ['data.recommend' => 'The Recommend field is required.'] );
    } );

    it( 'stores a score from the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.recommend', '9' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '9' );
    } );
} );

describe( 'display and export', function (): void {
    beforeEach( function (): void {
        ( $this->submitApi )( '9' )->assertCreated();

        $this->submission = $this->form->submissions()->first();
    } );

    it( 'shows the score, with its category when enabled', function (): void {
        expect( $this->submission->values->first()->display_value )->toBe( '9' );

        $this->nps->update( ['field_config' => ['show_category' => true]] );

        expect( $this->submission->fresh( ['values'] )->values->first()->display_value )->toBe( '9 – Promoter' );
    } );

    it( 'exports a category column when enabled', function (): void {
        $this->nps->update( ['field_config' => ['show_category' => true]] );

        $fields = $this->form->fields()->get();
        $export = app( ExportService::class );

        expect( $this->nps->fresh()->fieldType()->exportColumns( $this->nps->fresh() ) )->toBe( ['Recommend', 'Recommend (Category)'] )
            ->and( $export->buildRow( $this->form, $this->submission->fresh( ['values'] ), $fields ) )->toContain( '9', 'Promoter' );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders 0 to 10 as radios with the end labels', function (): void {
        $html = FieldRenderer::render( $this->nps, '7' );

        expect( $html )->toContain( 'role="radiogroup"' )
            ->toContain( 'id="field-recommend-0"' )
            ->toContain( 'id="field-recommend-10"' )
            ->not->toContain( 'id="field-recommend-11"' )
            ->toContain( '<span class="sr-only">, Not likely</span>' )
            ->toContain( '<span class="sr-only">, Extremely likely</span>' )
            ->toContain( 'aria-describedby="field-recommend-ends' )
            ->toContain( '0 = Not likely' )
            ->toMatch( '/value="7"\s+id="field-recommend-7"\s+class="peer sr-only"\s+checked/' );
    } );

    it( 'sends the end labels to React and Vue', function (): void {
        $resource = ( new FormFieldResource( $this->nps ) )->toArray( Request::create( '/' ) );

        expect( $resource['nps'] )->toBe( ['low' => 'Not likely', 'high' => 'Extremely likely'] );
    } );
} );
