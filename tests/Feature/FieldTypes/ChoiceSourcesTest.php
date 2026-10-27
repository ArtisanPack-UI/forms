<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\ChoiceSources;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #107: choice fields that load their options from a source
 * registered through the `ap.forms.choiceSources` filter.
 */
beforeEach( function (): void {
    $this->resolverCalls = 0;

    addFilter( 'ap.forms.choiceSources', function ( array $sources ): array {
        $sources['locations'] = [
            'label'    => 'Store locations',
            'resolver' => function ( FormField $field ): array {
                $this->resolverCalls++;

                return ['north' => 'North Store', 'south' => 'South Store'];
            },
        ];
        $sources['broken']  = ['label' => 'Broken', 'resolver' => fn (): array => throw new RuntimeException( 'Database is down' )];
        $sources['invalid'] = ['label' => 'No resolver'];

        return $sources;
    } );

    app( ChoiceSources::class )->flush();

    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->field = FormField::factory()->for( $this->form )->create( [
        'type'         => 'select',
        'name'         => 'store',
        'label'        => 'Store',
        'field_config' => ['choices_source' => 'locations', 'options' => [['value' => 'manual', 'label' => 'Manual']]],
    ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.forms.choiceSources' );
} );

describe( 'choice source registry', function (): void {
    it( 'lists valid sources only', function (): void {
        expect( app( ChoiceSources::class )->options() )->toBe( [
            ['value' => 'locations', 'label' => 'Store locations'],
            ['value' => 'broken', 'label' => 'Broken'],
        ] );
    } );

    it( 'offers the sources in the "Load Choices From" setting', function ( string $type ): void {
        $setting = FieldSettings::find( FormField::factory()->make( ['type' => $type] ), 'choices_source' );

        expect( $setting['input'] )->toBe( 'select' )
            ->and( $setting['options'] )->toBe( [
                ['value' => '', 'label' => 'Options list'],
                ['value' => 'locations', 'label' => 'Store locations'],
                ['value' => 'broken', 'label' => 'Broken'],
            ] )
            ->and( $setting )->not->toHaveKey( 'options_from' );
    } )->with( ['select', 'radio', 'checkbox_group', 'select_multiple'] );

    it( 'leaves the setting out while no source is registered', function (): void {
        removeAllFilters( 'ap.forms.choiceSources' );
        app( ChoiceSources::class )->flush();

        expect( FieldSettings::find( FormField::factory()->make( ['type' => 'select'] ), 'choices_source' ) )->toBeNull();
    } );

    it( 'stores only a registered source from the builder', function (): void {
        $component = Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )->call( 'selectField', $this->field->uuid );

        $component->call( 'updateSetting', 'choices_source', 'invalid' );
        expect( $this->field->fresh()->getConfig( 'choices_source' ) )->toBeNull();

        $component->call( 'updateSetting', 'choices_source', 'locations' );
        expect( $this->field->fresh()->getConfig( 'choices_source' ) )->toBe( 'locations' );
    } );
} );

describe( 'resolved choices', function (): void {
    it( 'replace the stored options', function (): void {
        expect( $this->field->options )->toBe( [['value' => 'north', 'label' => 'North Store'], ['value' => 'south', 'label' => 'South Store']] )
            ->and( $this->field->getChoicesSource() )->toBe( 'locations' );
    } );

    it( 'are resolved once per request for each field', function (): void {
        $field = $this->field->fresh();

        $field->options;
        $field->options;
        $this->field->options;
        $field->getChoiceDisplayLabel( 'north' );

        expect( $this->resolverCalls )->toBe( 1 );
    } );

    it( 'fall back to the stored options for an unknown source', function (): void {
        $this->field->update( ['field_config' => ['choices_source' => 'gone', 'options' => [['value' => 'manual', 'label' => 'Manual']]]] );

        expect( $this->field->fresh()->getChoicesSource() )->toBeNull()
            ->and( $this->field->fresh()->options )->toBe( [['value' => 'manual', 'label' => 'Manual']] );
    } );

    it( 'are empty and logged when the resolver fails', function (): void {
        Log::spy();
        $this->field->update( ['field_config' => ['choices_source' => 'broken']] );

        expect( $this->field->fresh()->options )->toBe( [] );

        Log::shouldHaveReceived( 'warning' )->withArgs( fn ( string $message, array $context ): bool => 'broken' === $context['source'] && 'Database is down' === $context['error'] );
    } );

    it( 'are rendered by the Livewire renderer and sent in the render payload', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSee( 'North Store' )
            ->assertDontSee( 'Manual' );

        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['options'] )->toBe( [['value' => 'north', 'label' => 'North Store'], ['value' => 'south', 'label' => 'South Store']] );
    } );

    it( 'show their label for a stored value', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData.store', 'south' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values()->first()->display_value )->toBe( 'South Store' );
    } );

    it( 'are offered to conditional logic', function (): void {
        expect( $this->field->getConditionTargets()[0]['options'] )->toBe( [['value' => 'north', 'label' => 'North Store'], ['value' => 'south', 'label' => 'South Store']] );
    } );

    it( 'show as a notice instead of the options editor in the builder', function (): void {
        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->assertSee( 'Choices are loaded from' )
            ->assertSee( 'Store locations' )
            ->assertSee( 'North Store' )
            ->assertDontSee( 'Bulk Add / Predefined Choices' );
    } );
} );

describe( 'validation against resolved choices', function (): void {
    it( 'rejects a value the source did not resolve', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->afterSpamDelay()
            ->set( 'formData.store', 'manual' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.store' => 'in'] )
            ->assertSee( 'Please choose one of the available options for Store.' );
    } );

    it( 'checks each value of a multi-choice field', function (): void {
        $this->field->update( ['type' => 'checkbox_group'] );
        $rules = app( SubmissionService::class )->buildValidationRules( $this->form->fields()->get() );

        expect( $rules['formData.store.*'] )->toBe( ['string', 'in:"north","south"'] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['store' => ['north', 'east']],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.store.1' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['store' => ['north', 'south']],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();
    } );

    it( 'is skipped when the field offers an "Other" choice', function (): void {
        $this->field->update( ['type' => 'radio', 'field_config' => ['choices_source' => 'locations', 'enable_other' => true]] );

        expect( $this->field->fresh()->buildValidationRules() )->not->toContain( 'in:"north","south"' );
    } );

    it( 'uses the static options list when the field has no source', function (): void {
        $this->field->update( ['field_config' => ['options' => [['value' => 'manual', 'label' => 'Manual']]]] );

        expect( $this->field->fresh()->buildValidationRules() )->toContain( 'in:"manual"' );
    } );

    it( 'uses the custom error message', function (): void {
        $this->field->update( ['field_config' => ['choices_source' => 'locations', 'error_message' => 'Pick a store we have.']] );

        expect( $this->field->fresh()->buildValidationMessages()['in'] )->toBe( 'Pick a store we have.' );
    } );
} );
