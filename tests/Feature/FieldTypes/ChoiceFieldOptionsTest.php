<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\PopulationContext;
use Illuminate\Http\Request;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * Guards issues #105 (per-option defaults, choice layout, randomized order,
 * "Select All") and #106 (searchable select).
 */
$options = static fn ( array $defaults = [] ): array => array_map(
    fn ( string $value ): array => ['value' => $value, 'label' => ucfirst( $value )] + ( in_array( $value, $defaults, true ) ? ['default' => true] : [] ),
    ['red', 'green', 'blue', 'yellow', 'purple', 'orange'],
);

describe( 'choice settings', function (): void {
    it( 'offer layout and randomize on radio and checkbox groups', function ( string $type ): void {
        $field = FormField::factory()->make( ['type' => $type] );

        expect( FieldSettings::find( $field, 'layout' ) )->toMatchArray( ['input' => 'select', 'default' => 'stacked', 'tab' => 'general'] )
            ->and( array_column( FieldSettings::find( $field, 'layout' )['options'], 'value' ) )->toBe( FormField::CHOICE_LAYOUTS )
            ->and( FieldSettings::find( $field, 'randomize' ) )->toMatchArray( ['input' => 'toggle'] )
            ->and( FieldSettings::find( $field, 'searchable' ) )->toBeNull();
    } )->with( ['radio', 'checkbox_group'] );

    it( 'offer "Select All" on checkbox groups only', function (): void {
        expect( FieldSettings::find( FormField::factory()->make( ['type' => 'checkbox_group'] ), 'select_all' ) )->toMatchArray( ['input' => 'toggle'] )
            ->and( FieldSettings::find( FormField::factory()->make( ['type' => 'radio'] ), 'select_all' ) )->toBeNull();
    } );

    it( 'offer search on selects and multi-selects only', function ( string $type, bool $offered ): void {
        $setting = FieldSettings::find( FormField::factory()->make( ['type' => $type] ), 'searchable' );

        $offered ? expect( $setting )->toMatchArray( ['input' => 'toggle'] ) : expect( $setting )->toBeNull();
    } )->with( [['select', true], ['select_multiple', true], ['radio', false], ['checkbox_group', false]] );

    it( 'are ignored on types that do not offer them', function (): void {
        $field = FormField::factory()->make( ['type' => 'select', 'field_config' => ['layout' => '2-col', 'randomize' => true, 'select_all' => true]] );

        expect( $field->getChoiceLayout() )->toBe( 'stacked' )
            ->and( $field->randomizesChoices() )->toBeFalse()
            ->and( $field->offersSelectAll() )->toBeFalse();
    } );
} );

describe( 'per-option defaults', function () use ( $options ): void {
    it( 'start a radio or select with the first marked option', function ( string $type ) use ( $options ): void {
        $field = FormField::factory()->make( ['type' => $type, 'default_value' => 'green', 'field_config' => ['options' => $options( ['blue', 'yellow'] )]] );

        expect( $field->getDefaultOptionValues() )->toBe( ['blue'] )
            ->and( DynamicPopulation::initialValue( $field, PopulationContext::trusted() ) )->toBe( 'blue' );
    } )->with( ['radio', 'select'] );

    it( 'start a checkbox group or multi-select with every marked option', function ( string $type ) use ( $options ): void {
        $field = FormField::factory()->make( ['type' => $type, 'field_config' => ['options' => $options( ['blue', 'yellow'] )]] );

        expect( DynamicPopulation::initialValue( $field, PopulationContext::trusted() ) )->toBe( ['blue', 'yellow'] );
    } )->with( ['checkbox_group', 'select_multiple'] );

    it( 'fall back to the default value when no option is marked', function () use ( $options ): void {
        $field = FormField::factory()->make( ['type' => 'radio', 'default_value' => 'green', 'field_config' => ['options' => $options()]] );

        expect( DynamicPopulation::initialValue( $field, PopulationContext::trusted() ) )->toBe( 'green' );
    } );

    it( 'give way to a populated value', function () use ( $options ): void {
        $field = FormField::factory()->make( [
            'type'         => 'checkbox_group',
            'name'         => 'colors',
            'field_config' => ['options' => $options( ['blue'] ), 'dynamic_population' => true],
        ] );

        expect( DynamicPopulation::initialValue( $field, PopulationContext::trusted(), ['colors' => 'red,purple'] ) )->toBe( ['red', 'purple'] )
            ->and( DynamicPopulation::initialValue( $field, PopulationContext::trusted(), ['colors' => 'nope'] ) )->toBe( ['blue'] );
    } );

    it( 'are what a read-only choice is submitted with', function () use ( $options ): void {
        $field = FormField::factory()->make( ['type' => 'select', 'field_config' => ['options' => $options( ['purple'] ), 'read_only' => true]] );

        expect( $field->getReadOnlyValue() )->toBe( 'purple' );
    } );

    it( 'seed the Livewire renderer and the render payload', function () use ( $options ): void {
        $form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $form )->create( ['type' => 'checkbox_group', 'name' => 'colors', 'field_config' => ['options' => $options( ['red', 'blue'] )]] );

        Livewire::test( FormRenderer::class, ['form' => $form] )->assertSet( 'formData.colors', ['red', 'blue'] );

        $fields = ( new FormRenderResource( $form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['resolved_default_value'] )->toBe( ['red', 'blue'] )
            ->and( $fields[0]['options'][0] )->toBe( ['value' => 'red', 'label' => 'Red', 'default' => true] );
    } );
} );

describe( 'choice layout', function () use ( $options ): void {
    it( 'arranges radio and checkbox groups with the layout classes', function ( string $type, string $layout, string $classes ) use ( $options ): void {
        $field = FormField::factory()->make( ['type' => $type, 'name' => 'colors', 'field_config' => ['options' => $options(), 'layout' => $layout]] );

        expect( $field->getChoiceLayoutClasses() )->toBe( $classes )
            ->and( FieldRenderer::render( $field, 'checkbox_group' === $type ? [] : '' ) )->toContain( 'class="' . $classes );
    } )->with( [
        ['radio', 'stacked', 'flex flex-col gap-2'],
        ['radio', 'inline', 'flex flex-wrap gap-x-6 gap-y-2'],
        ['checkbox_group', '2-col', 'grid grid-cols-1 gap-2 sm:grid-cols-2'],
        ['checkbox_group', '3-col', 'grid grid-cols-1 gap-2 sm:grid-cols-3'],
    ] );

    it( 'falls back to stacked for an unknown layout', function () use ( $options ): void {
        expect( FormField::factory()->make( ['type' => 'radio', 'field_config' => ['options' => $options(), 'layout' => 'grid']] )->getChoiceLayout() )->toBe( 'stacked' );
    } );

    it( 'keeps the radiogroup and DOM order in column layouts', function () use ( $options ): void {
        $html = FieldRenderer::render( FormField::factory()->make( ['type' => 'radio', 'name' => 'colors', 'field_config' => ['options' => $options(), 'layout' => '3-col']] ), '' );

        expect( $html )->toContain( 'role="radiogroup"' )
            ->and( strpos( $html, 'value="red"' ) )->toBeLessThan( strpos( $html, 'value="green"' ) )
            ->and( strpos( $html, 'value="green"' ) )->toBeLessThan( strpos( $html, 'value="blue"' ) );
    } );
} );

describe( 'randomized choices', function () use ( $options ): void {
    beforeEach( function () use ( $options ): void {
        $this->form  = Form::factory()->create( ['is_active' => true] );
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'radio',
            'name'         => 'colors',
            'field_config' => ['options' => $options(), 'randomize' => true, 'enable_other' => true],
        ] );
    } );

    it( 'shuffle the same way for the same seed and keep every option', function (): void {
        $first  = array_column( $this->field->fresh()->withChoiceSeed( 42 )->getRenderOptions(), 'value' );
        $second = array_column( $this->field->fresh()->withChoiceSeed( 42 )->getRenderOptions(), 'value' );
        $seeds  = collect( range( 1, 20 ) )->map( fn ( int $seed ): array => array_column( $this->field->fresh()->withChoiceSeed( $seed )->getRenderOptions(), 'value' ) );

        expect( $first )->toBe( $second )
            ->and( collect( $first )->sort()->values()->all() )->toBe( ['blue', 'green', 'orange', 'purple', 'red', 'yellow'] )
            ->and( $seeds->unique( fn ( array $order ): string => implode( ',', $order ) )->count() )->toBeGreaterThan( 1 );
    } );

    it( 'keep the stored order when off', function () use ( $options ): void {
        $field = FormField::factory()->make( ['type' => 'radio', 'field_config' => ['options' => $options()]] );

        expect( array_column( $field->withChoiceSeed( 42 )->getRenderOptions(), 'value' ) )->toBe( ['red', 'green', 'blue', 'yellow', 'purple', 'orange'] );
    } );

    it( 'keep their order across Livewire re-renders and leave "Other" last', function (): void {
        $component = Livewire::test( FormRenderer::class, ['form' => $this->form] );
        $order     = fn (): array => collect( ['red', 'green', 'blue', 'yellow', 'purple', 'orange', '__other__'] )
            ->sortBy( fn ( string $value ): int => strpos( $component->html(), 'value="' . $value . '"' ) )
            ->values()
            ->all();

        $before = $order();
        $component->set( 'formData.colors', 'blue' );

        expect( $order() )->toBe( $before )
            ->and( $before[6] )->toBe( '__other__' );
    } );

    it( 'lock the seed on the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'choiceSeed', 1 );
    } )->throws( CannotUpdateLockedPropertyException::class );
} );

describe( '"Select All"', function () use ( $options ): void {
    it( 'renders a checkbox that controls every choice', function () use ( $options ): void {
        $field = FormField::factory()->make( ['type' => 'checkbox_group', 'name' => 'colors', 'field_config' => ['options' => $options(), 'select_all' => true]] );
        $html  = FieldRenderer::render( $field, [] );

        expect( $html )->toContain( 'id="field-colors-select-all"' )
            ->toContain( 'Select All' )
            ->toContain( 'aria-controls="field-colors-0 field-colors-1 field-colors-2 field-colors-3 field-colors-4 field-colors-5"' )
            ->toContain( '$el.indeterminate' )
            ->and( strpos( $html, 'field-colors-select-all' ) )->toBeLessThan( strpos( $html, 'id="field-colors-0"' ) );
    } );

    it( 'is left out when off', function () use ( $options ): void {
        $field = FormField::factory()->make( ['type' => 'checkbox_group', 'name' => 'colors', 'field_config' => ['options' => $options()]] );

        expect( FieldRenderer::render( $field, [] ) )->not->toContain( 'select-all' );
    } );
} );

describe( 'searchable select', function () use ( $options ): void {
    it( 'renders an ARIA combobox with a listbox popup', function ( string $type, bool $multiple ) use ( $options ): void {
        $field = FormField::factory()->make( ['type' => $type, 'name' => 'color', 'label' => 'Color', 'is_required' => true, 'field_config' => ['options' => $options(), 'searchable' => true]] );
        $html  = FieldRenderer::render( $field, $multiple ? [] : '' );

        expect( $html )->toContain( 'role="combobox"' )
            ->toContain( 'aria-autocomplete="list"' )
            ->toContain( 'aria-controls="field-color-listbox"' )
            ->toContain( ':aria-activedescendant="activeId()"' )
            ->toContain( 'id="field-color-listbox"' )
            ->toContain( 'role="listbox"' )
            ->toContain( 'role="option"' )
            ->toContain( 'role="status"' )
            ->toContain( 'results available.' )
            ->toContain( 'aria-required="true"' )
            ->toContain( '<label' )
            ->toContain( 'for="field-color"' )
            ->toContain( "\$wire.entangle('formData.color').live" );

        $multiple
            ? expect( $html )->toContain( 'aria-multiselectable="true"' )->toContain( 'Selected options' )
            : expect( $html )->not->toContain( 'aria-multiselectable' );
    } )->with( [['select', false], ['select_multiple', true]] );

    it( 'keeps the plain select when off', function () use ( $options ): void {
        $html = FieldRenderer::render( FormField::factory()->make( ['type' => 'select', 'name' => 'color', 'field_config' => ['options' => $options()]] ), '' );

        expect( $html )->not->toContain( 'role="combobox"' );
    } );

    it( 'is read-only when the field is', function () use ( $options ): void {
        $html = FieldRenderer::render( FormField::factory()->make( ['type' => 'select', 'name' => 'color', 'field_config' => ['options' => $options(), 'searchable' => true, 'read_only' => true]] ), '' );

        expect( $html )->toContain( 'readonly aria-readonly="true"' );
    } );

    it( 'still validates and stores the chosen option', function () use ( $options ): void {
        $form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $form )->required()->create( ['type' => 'select_multiple', 'name' => 'colors', 'field_config' => ['options' => $options(), 'searchable' => true]] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.colors' => 'required'] )
            ->set( 'formData.colors', ['red', 'blue'] )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $form->submissions()->first()->values()->first()->value_array )->toBe( ['red', 'blue'] );
    } );
} );
