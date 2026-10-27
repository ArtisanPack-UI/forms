<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;
use ArtisanPackUI\Forms\FieldTypes\NameFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\PopulationContext;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #109: the Name field (prefix, first, middle, last and suffix
 * with per-part toggles, and the simple, normal and extended formats).
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );
    $this->name = FormField::factory()->for( $this->form )->required()->create( [
        'type'  => 'name',
        'name'  => 'full_name',
        'label' => 'Your name',
    ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.forms.fieldValue.first' );
} );

describe( 'definition', function (): void {
    it( 'is a registered composite type in the advanced category', function (): void {
        expect( $this->name->fieldType() )->toBeInstanceOf( NameFieldType::class )
            ->and( $this->name->fieldType() )->toBeInstanceOf( CompositeFieldType::class )
            ->and( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'name' )
            ->and( FieldTypes::getDefaults( 'name' ) )->toMatchArray( ['label' => 'Name'] );
    } );

    it( 'defaults to the normal format: first and last on, the other parts off', function (): void {
        $subInputs = $this->name->getSubInputs();

        expect( array_keys( $subInputs ) )->toBe( ['prefix', 'first', 'middle', 'last', 'suffix'] )
            ->and( array_keys( $this->name->getEnabledSubInputs() ) )->toBe( ['first', 'last'] )
            ->and( $this->name->getRequiredSubInputKeys() )->toBe( ['first', 'last'] )
            ->and( array_column( $subInputs, 'autocomplete', 'key' ) )->toBe( [
                'prefix' => 'honorific-prefix',
                'first'  => 'given-name',
                'middle' => 'additional-name',
                'last'   => 'family-name',
                'suffix' => 'honorific-suffix',
            ] );
    } );

    it( 'shows all five parts in the extended format', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'extended']] );

        expect( array_keys( $this->name->fresh()->getEnabledSubInputs() ) )->toBe( ['prefix', 'first', 'middle', 'last', 'suffix'] );
    } );

    it( 'asks for the whole name in one input in the simple format', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'simple']] );
        $subInputs = $this->name->fresh()->getSubInputs();

        expect( array_keys( $subInputs ) )->toBe( ['full'] )
            ->and( $subInputs['full'] )->toMatchArray( ['autocomplete' => 'name', 'required' => true, 'enabled' => true] );
    } );

    it( 'lets an admin turn parts on and off per field', function (): void {
        $this->name->update( ['field_config' => ['sub_inputs' => ['middle' => ['enabled' => true], 'last' => ['enabled' => false]]]] );

        expect( array_keys( $this->name->fresh()->getEnabledSubInputs() ) )->toBe( ['first', 'middle'] );
    } );

    it( 'offers the format, prefix and suffix settings', function (): void {
        $keys = array_column( FieldSettings::schema( $this->name ), 'key' );

        expect( $keys )->toContain( 'name_format', 'prefix_choices', 'suffix_input', 'suffix_choices', 'sub_label_placement', 'export_format' )
            ->not->toContain( 'autocomplete', 'dynamic_population' );
    } );
} );

describe( 'prefix and suffix choices', function (): void {
    it( 'makes the prefix a select of the default prefixes', function (): void {
        $prefix = $this->name->getSubInputs()['prefix'];

        expect( $prefix['type'] )->toBe( 'select' )
            ->and( array_column( $prefix['options'], 'value' ) )->toBe( ['Mr.', 'Mrs.', 'Ms.', 'Mx.', 'Dr.', 'Prof.', 'Rev.'] );
    } );

    it( 'uses the field\'s own prefix choices', function (): void {
        $this->name->update( ['field_config' => ['prefix_choices' => ['Dr.', ' Sir ', '', 'Dr.']]] );

        expect( array_column( $this->name->fresh()->getSubInputs()['prefix']['options'], 'value' ) )->toBe( ['Dr.', 'Sir'] );
    } );

    it( 'keeps the suffix free text unless it is set to a select', function (): void {
        expect( $this->name->getSubInputs()['suffix']['type'] )->toBe( 'text' );

        $this->name->update( ['field_config' => ['suffix_input' => 'select', 'suffix_choices' => ['Jr.', 'Esq.']]] );
        $suffix = $this->name->fresh()->getSubInputs()['suffix'];

        expect( $suffix['type'] )->toBe( 'select' )
            ->and( array_column( $suffix['options'], 'value' ) )->toBe( ['Jr.', 'Esq.'] );
    } );

    it( 'rejects a prefix that is not one of the choices', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'extended']] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData.full_name.prefix', 'Captain' )
            ->set( 'formData.full_name.first', 'Jane' )
            ->set( 'formData.full_name.last', 'Doe' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.full_name.prefix' => 'in'] )
            ->assertSee( 'Your name: please choose a valid Prefix.' );
    } );
} );

describe( 'validation and storage', function (): void {
    it( 'requires first and last on a required field', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData.full_name.first', 'Jane' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.full_name.last' => 'required'] );
    } );

    it( 'stores the parts and shows the full name, skipping empty parts', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'extended']] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData.full_name.prefix', 'Dr.' )
            ->set( 'formData.full_name.first', ' Jane ' )
            ->set( 'formData.full_name.last', 'Doe' )
            ->set( 'formData.full_name.suffix', 'PhD' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value_array )->toBe( ['prefix' => 'Dr.', 'first' => 'Jane', 'middle' => '', 'last' => 'Doe', 'suffix' => 'PhD'] )
            ->and( $value->display_value )->toBe( 'Dr. Jane Doe PhD' );
    } );

    it( 'stores a simple name as one part', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'simple']] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['full_name' => ['full' => 'Mononym']],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values()->first()->display_value )->toBe( 'Mononym' );
    } );
} );

describe( 'merge tags and export', function (): void {
    it( 'offers a tag for the full name and each enabled part', function (): void {
        expect( array_keys( $this->name->fieldType()->mergeTags( $this->name ) ) )->toBe( ['{full_name}', '{full_name:first}', '{full_name:last}'] );
    } );

    it( 'exports one column per enabled part', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['full_name' => ['first' => 'Jane', 'last' => 'Doe']] );
        $export     = app( ExportService::class );

        expect( $export->buildHeaders( $this->form ) )->toContain( 'Your name (First)', 'Your name (Last)' )
            ->not->toContain( 'Your name (Middle)' )
            ->and( $export->buildRow( $this->form, $submission->fresh(), $this->form->fields()->get() ) )->toContain( 'Jane', 'Doe' );
    } );
} );

describe( 'defaults and dynamic population', function (): void {
    it( 'starts each part with its default value', function (): void {
        $this->name->update( ['field_config' => ['sub_inputs' => ['last' => ['default' => 'Doe']]]] );

        expect( DynamicPopulation::initialValue( $this->name->fresh(), PopulationContext::trusted() ) )->toBe( ['first' => '', 'last' => 'Doe'] );
    } );

    it( 'fills a part from its parameter: prefill, then query string, then filter', function (): void {
        $this->name->update( ['field_config' => ['sub_inputs' => ['first' => ['parameter' => 'first'], 'last' => ['parameter' => 'surname']]]] );
        $field   = $this->name->fresh();
        $context = PopulationContext::fromPageRequest( Request::create( '/?first=Query&surname=<b>Doe</b>' ) );

        expect( DynamicPopulation::initialValue( $field, $context, ['first' => 'Prefilled'] ) )->toBe( ['first' => 'Prefilled', 'last' => 'Doe'] );

        addFilter( 'ap.forms.fieldValue.first', fn (): string => 'Filtered' );

        expect( DynamicPopulation::initialValue( $field, PopulationContext::trusted() ) )->toBe( ['first' => 'Filtered', 'last' => ''] );
    } );

    it( 'only populates a select part with one of its choices', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'extended', 'sub_inputs' => ['prefix' => ['parameter' => 'title', 'default' => 'Mx.']]]] );
        $field = $this->name->fresh();

        expect( DynamicPopulation::initialValue( $field, PopulationContext::trusted(), ['title' => 'dr.'] )['prefix'] )->toBe( 'Dr.' )
            ->and( DynamicPopulation::initialValue( $field, PopulationContext::trusted(), ['title' => 'Captain'] )['prefix'] )->toBe( 'Mx.' );
    } );

    it( 'cleans a parameter and keeps the last enabled part on', function (): void {
        $config = CompositeFieldType::updateSubInputConfig( $this->name, 'first', 'parameter', ' first name!<> ' );

        expect( $config['sub_inputs']['first']['parameter'] )->toBe( 'firstname' );

        $this->name->update( ['field_config' => ['sub_inputs' => ['last' => ['enabled' => false]]]] );

        expect( CompositeFieldType::updateSubInputConfig( $this->name->fresh(), 'first', 'enabled', false ) )->toBeNull();
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders a fieldset with a labelled, autocompleting input per part', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'extended']] );
        $html = FieldRenderer::render( $this->name->fresh(), [] );

        expect( $html )->toContain( '<legend' )
            ->toContain( 'Your name' )
            ->toContain( '<select' )
            ->toContain( 'autocomplete="honorific-prefix"' )
            ->toContain( 'autocomplete="given-name"' )
            ->toContain( 'wire:model.live="formData.full_name.first"' )
            ->toContain( 'data-forms-part="last"' )
            ->toContain( '<option value="Dr.">Dr.</option>' );
    } );

    it( 'sends the parts and prefix options to the React and Vue renderers', function (): void {
        $this->name->update( ['field_config' => ['name_format' => 'extended']] );
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( array_column( $fields[0]['sub_inputs'], 'key' ) )->toBe( ['prefix', 'first', 'middle', 'last', 'suffix'] )
            ->and( $fields[0]['sub_inputs'][0]['type'] )->toBe( 'select' )
            ->and( $fields[0]['sub_inputs'][0]['options'][0] )->toBe( ['value' => 'Mr.', 'label' => 'Mr.'] );
    } );
} );
