<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\MatrixFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #131: the Likert / matrix field.
 */
beforeEach( function (): void {
    $this->form   = Form::factory()->create( ['is_active' => true] );
    $this->matrix = FormField::factory()->for( $this->form )->create( [
        'type'         => 'matrix',
        'name'         => 'service',
        'label'        => 'Service',
        'help_text'    => null,
        'is_required'  => true,
        'field_config' => [
            'rows'    => "Fast\nFriendly",
            'columns' => "Disagree | 1\nNeutral | 2\nAgree | 3",
        ],
    ] );

    $this->submitApi = fn ( mixed $value ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['service' => $value],
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'registration', function (): void {
    it( 'is an advanced field with layout, rows and columns settings', function (): void {
        $config = FieldTypes::getTypeConfig( 'matrix' );

        expect( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'matrix' )
            ->and( $config )->toMatchArray( ['label' => 'Likert / Matrix', 'icon' => 'o-squares-2x2', 'supports_default_value' => false] )
            ->and( array_slice( array_column( $config['settings'], 'key' ), 0, 3 ) )->toBe( ['mode', 'rows', 'columns'] )
            ->and( ConditionalLogic::getOperatorsForType( 'matrix' ) )->toBe( [] );
    } );
} );

describe( 'rows and columns', function (): void {
    it( 'parses statements and choices', function (): void {
        expect( MatrixFieldType::rows( $this->matrix ) )->toBe( [['key' => 'fast', 'label' => 'Fast'], ['key' => 'friendly', 'label' => 'Friendly']] )
            ->and( MatrixFieldType::columns( $this->matrix ) )->toBe( [
                ['value' => '1', 'label' => 'Disagree'],
                ['value' => '2', 'label' => 'Neutral'],
                ['value' => '3', 'label' => 'Agree'],
            ] );
    } );

    it( 'keeps row keys unique and never numeric', function (): void {
        expect( array_column( MatrixFieldType::parseRows( "Fast\nfast\n!!!\n2024\n\n" ), 'key' ) )->toBe( ['fast', 'fast_2', 'row_3', 'row_2024'] )
            ->and( MatrixFieldType::parseRows( implode( "\n", range( 'a', 'z' ) ) . "\n" . implode( "\n", range( 1, 10 ) ) ) )->toHaveCount( MatrixFieldType::MAX_ROWS );
    } );

    it( 'skips repeated column values and falls back to the agreement scale', function (): void {
        expect( MatrixFieldType::parseColumns( "Yes\nyes | Yes\nNo" ) )->toBe( [['value' => 'Yes', 'label' => 'Yes'], ['value' => 'No', 'label' => 'No']] )
            ->and( array_column( MatrixFieldType::columns( FormField::factory()->make( ['type' => 'matrix', 'field_config' => []] ) ), 'label' ) )
            ->toBe( ['Strongly disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly agree'] );
    } );

    it( 'is a single scale in single mode or without rows', function (): void {
        expect( MatrixFieldType::isSingle( $this->matrix ) )->toBeFalse()
            ->and( MatrixFieldType::isSingle( FormField::factory()->make( ['type' => 'matrix', 'field_config' => ['rows' => "A\nB", 'mode' => 'single']] ) ) )->toBeTrue()
            ->and( MatrixFieldType::isSingle( FormField::factory()->make( ['type' => 'matrix', 'field_config' => ['rows' => '']] ) ) )->toBeTrue();
    } );

    it( 'starts with every row unanswered', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )->assertSet( 'formData.service', ['fast' => '', 'friendly' => ''] );
    } );
} );

describe( 'validation and storage', function (): void {
    it( 'needs every row of a required matrix', function (): void {
        ( $this->submitApi )( ['fast' => '3'] )->assertUnprocessable()->assertJsonValidationErrors( [
            'data.service.friendly' => 'Service: please answer "Friendly".',
        ] );
    } );

    it( 'rejects answers that are not a column and rows it does not have', function (): void {
        ( $this->submitApi )( ['fast' => '9', 'friendly' => '1'] )->assertUnprocessable()->assertJsonValidationErrors( [
            'data.service.fast' => 'Service: please choose a valid answer for "Fast".',
        ] );

        ( $this->submitApi )( ['fast' => '1', 'friendly' => '1', 'other' => '1'] )->assertUnprocessable()->assertJsonValidationErrors( ['data.service' => 'The Service field is invalid.'] );
    } );

    it( 'stores each answer keyed by row with its statement and answer label', function (): void {
        ( $this->submitApi )( ['fast' => '3', 'friendly' => '1'] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value_array )->toBe( [
            'fast'     => ['statement' => 'Fast', 'answer' => 'Agree', 'value' => '3'],
            'friendly' => ['statement' => 'Friendly', 'answer' => 'Disagree', 'value' => '1'],
        ] );
    } );

    it( 'keeps reading stored answers after the rows and columns change', function (): void {
        ( $this->submitApi )( ['fast' => '3', 'friendly' => '1'] )->assertCreated();

        $this->matrix->update( ['field_config' => ['rows' => 'Quick', 'columns' => "No\nYes"]] );

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->display_value )->toBe( "Fast: Agree\nFriendly: Disagree" );
    } );

    it( 'still reads answers stored as bare column values', function (): void {
        $submission = FormSubmission::factory()->for( $this->form )->create();
        $value      = FormSubmissionValue::factory()->create( [
            'submission_id' => $submission->id,
            'field_id'      => $this->matrix->id,
            'field_type'    => 'matrix',
            'value'         => null,
            'value_array'   => ['fast' => '3'],
        ] );

        expect( $value->fresh()->display_value )->toBe( 'Fast: Agree' );
    } );

    it( 'stores only the answered rows of an optional matrix', function (): void {
        $this->matrix->update( ['is_required' => false] );

        ( $this->submitApi )( ['fast' => '2', 'friendly' => ''] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value_array )->toBe( ['fast' => ['statement' => 'Fast', 'answer' => 'Neutral', 'value' => '2']] );
    } );

    it( 'validates rows in the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.service.fast', '3' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.service.friendly' => 'required'] )
            ->set( 'formData.service.friendly', '2' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value_array )->toBe( ['fast' => ['statement' => 'Fast', 'answer' => 'Agree', 'value' => '3'], 'friendly' => ['statement' => 'Friendly', 'answer' => 'Neutral', 'value' => '2']] );
    } );

    it( 'stores a single scale as its chosen value', function (): void {
        $this->matrix->update( ['field_config' => array_merge( $this->matrix->field_config, ['mode' => 'single'] )] );

        ( $this->submitApi )( '7' )->assertUnprocessable()->assertJsonValidationErrors( ['data.service' => 'Please choose one of the available answers for Service.'] );
        ( $this->submitApi )( '3' )->assertCreated();

        $value = $this->form->submissions()->first()->values->first();

        expect( $value->value )->toBe( '3' )
            ->and( $value->display_value )->toBe( 'Agree' );
    } );
} );

describe( 'display and export', function (): void {
    beforeEach( function (): void {
        ( $this->submitApi )( ['fast' => '3', 'friendly' => '1'] )->assertCreated();

        $this->submission = $this->form->submissions()->first();
    } );

    it( 'shows one row per line with the answer labels', function (): void {
        $value = $this->submission->values->first();

        expect( $value->display_value )->toBe( "Fast: Agree\nFriendly: Disagree" )
            ->and( $value->getStructuredValue() )->toBe( ['fast' => ['statement' => 'Fast', 'answer' => 'Agree', 'value' => '3'], 'friendly' => ['statement' => 'Friendly', 'answer' => 'Disagree', 'value' => '1']] )
            ->and( app( NotificationService::class )->formatAllFieldsAsTable( $this->submission->fresh( ['values'] ) ) )->toContain( "Fast: Agree<br>\nFriendly: Disagree" );
    } );

    it( 'exports a column per row', function (): void {
        $fields = $this->form->fields()->get();

        expect( $this->matrix->fieldType()->exportColumns( $this->matrix ) )->toBe( ['Service: Fast', 'Service: Friendly'] )
            ->and( app( ExportService::class )->buildRow( $this->form, $this->submission->fresh( ['values'] ), $fields ) )->toContain( 'Agree', 'Disagree' );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders a table whose radios are named by their row and column', function (): void {
        $html = FieldRenderer::render( $this->matrix, ['fast' => '3', 'friendly' => ''] );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( '<legend' )
            ->toContain( '<th scope="col" id="field-service-col-0"' )
            ->toContain( '<th scope="row"' )
            ->toContain( 'aria-labelledby="field-service-row-fast field-service-col-2"' )
            ->toContain( 'name="service[fast]"' )
            ->toContain( 'wire:model.live="formData.service.friendly"' )
            ->toMatch( '/value="3"\s+id="field-service-row-fast-2"\s+class="radio radio-primary"\s+aria-labelledby="[^"]+"\s+checked/' );
    } );

    it( 'shows a row error under its statement', function (): void {
        $errors = ( new ViewErrorBag() )->put( 'default', new MessageBag( ['formData.service.friendly' => ['Service: please answer "Friendly".']] ) );
        view()->share( 'errors', $errors );

        $html = FieldRenderer::render( $this->matrix, ['fast' => '', 'friendly' => ''] );

        expect( $html )->toContain( 'id="field-service-row-friendly-error"' )
            ->toContain( 'aria-describedby="field-service-row-friendly-error"' )
            ->toContain( 'Please answer 1 highlighted row in Service.' );
    } );

    it( 'renders a single scale as one radio group', function (): void {
        $this->matrix->update( ['field_config' => array_merge( $this->matrix->field_config, ['mode' => 'single'] )] );

        $html = FieldRenderer::render( $this->matrix->fresh(), '' );

        expect( $html )->toContain( 'role="radiogroup"' )
            ->not->toContain( '<table' )
            ->toContain( 'wire:model.live="formData.service"' );
    } );

    it( 'sends the rows and columns to React and Vue', function (): void {
        $resource = ( new FormFieldResource( $this->matrix ) )->toArray( Request::create( '/' ) );

        expect( $resource['matrix'] )->toMatchArray( ['single' => false] )
            ->and( array_column( $resource['matrix']['rows'], 'key' ) )->toBe( ['fast', 'friendly'] )
            ->and( array_column( $resource['matrix']['columns'], 'value' ) )->toBe( ['1', '2', '3'] );
    } );
} );
