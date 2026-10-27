<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

/**
 * Guards issue #127: the list (repeater) field.
 */
beforeEach( function (): void {
    $this->form       = Form::factory()->create( ['is_active' => true] );
    $this->attendees  = FormField::factory()->for( $this->form )->create( [
        'type'         => 'list',
        'name'         => 'attendees',
        'label'        => 'Attendees',
        'help_text'    => null,
        'field_config' => [
            'columns'   => "Name\nEmail | email\nRole | select | Speaker, Guest\nAge | number",
            'row_label' => 'Attendee',
            'max_rows'  => 3,
        ],
    ] );

    $this->submitApi = fn ( array $rows ) => $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['attendees' => $rows],
        '_form_loaded_at' => time() - 10,
    ] );
} );

describe( 'columns', function (): void {
    it( 'parses one column per line', function (): void {
        expect( ListFieldType::columns( $this->attendees ) )->toBe( [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'options' => []],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'options' => []],
            ['key' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => [['value' => 'Speaker', 'label' => 'Speaker'], ['value' => 'Guest', 'label' => 'Guest']]],
            ['key' => 'age', 'label' => 'Age', 'type' => 'number', 'options' => []],
        ] );
    } );

    it( 'falls back to text, numbers repeated keys and caps the columns', function (): void {
        $columns = ListFieldType::parseColumns( "Phone | tel\nChoice | select\n\nName\nName\n!!!\n" . implode( "\n", range( 1, 20 ) ) );

        expect( array_slice( array_column( $columns, 'type' ), 0, 2 ) )->toBe( ['text', 'text'] )
            ->and( array_slice( array_column( $columns, 'key' ), 0, 6 ) )->toBe( ['phone', 'choice', 'name', 'name_2', 'column_5', '1'] )
            ->and( $columns )->toHaveCount( ListFieldType::MAX_COLUMNS )
            ->and( ListFieldType::parseColumns( null ) )->toBe( [] );
    } );

    it( 'offers the list settings', function (): void {
        expect( FieldSettings::find( $this->attendees, 'columns' ) )->toMatchArray( ['input' => 'textarea'] )
            ->and( FieldSettings::find( $this->attendees, 'min_rows' ) )->toMatchArray( ['input' => 'integer', 'tab' => 'validation'] )
            ->and( FieldSettings::find( $this->attendees, 'max_rows' ) )->toMatchArray( ['input' => 'integer', 'tab' => 'validation'] )
            ->and( FieldSettings::find( $this->attendees, 'row_label' ) )->not->toBeNull()
            ->and( FieldSettings::find( $this->attendees, 'export_format' ) )->toMatchArray( ['tab' => 'advanced', 'default' => 'lines'] );
    } );

    it( 'reads the row limits and label', function (): void {
        expect( ListFieldType::maxRows( $this->attendees ) )->toBe( 3 )
            ->and( ListFieldType::minRows( $this->attendees ) )->toBeNull()
            ->and( ListFieldType::rowLabel( $this->attendees ) )->toBe( 'Attendee' );

        $this->attendees->update( ['field_config' => ['min_rows' => 5, 'max_rows' => 2]] );
        $field = $this->attendees->fresh();

        expect( ListFieldType::minRows( $field ) )->toBe( 2 )
            ->and( ListFieldType::rowLabel( $field ) )->toBe( 'Row' )
            ->and( ListFieldType::maxRows( FormField::factory()->make( ['type' => 'list'] ) ) )->toBe( ListFieldType::MAX_ROWS );
    } );
} );

describe( 'rows', function (): void {
    it( 'keeps the filled rows', function (): void {
        $rows = [
            ['name' => ' Ada ', 'email' => '', 'role' => '', 'age' => '', 'extra' => 'dropped'],
            ['name' => '', 'email' => '', 'role' => '', 'age' => ''],
            ['name' => 'Grace', 'email' => 'grace@example.com', 'role' => 'Speaker', 'age' => 85],
        ];

        expect( ListFieldType::filledRows( $this->attendees, $rows ) )->toBe( [
            ['name' => 'Ada', 'email' => '', 'role' => '', 'age' => ''],
            ['name' => 'Grace', 'email' => 'grace@example.com', 'role' => 'Speaker', 'age' => '85'],
        ] )
            ->and( array_keys( ListFieldType::filledRows( $this->attendees, $rows, false ) ) )->toBe( [0, 2] )
            ->and( ListFieldType::filledRows( $this->attendees, [['name' => '']] ) )->toBeNull()
            ->and( ListFieldType::filledRows( $this->attendees, 'text' ) )->toBeNull();
    } );

    it( 'starts with as many empty rows as its minimum', function (): void {
        expect( ListFieldType::initialRows( $this->attendees ) )->toBe( [['name' => '', 'email' => '', 'role' => '', 'age' => '']] );

        $this->attendees->update( ['field_config' => array_merge( $this->attendees->field_config, ['min_rows' => 2] )] );

        expect( ListFieldType::initialRows( $this->attendees->fresh() ) )->toHaveCount( 2 );
    } );
} );

describe( 'validation', function (): void {
    it( 'checks the row count and each column\'s cells', function (): void {
        $rules = $this->attendees->buildKeyedValidationRules();

        expect( $rules[''] )->toBe( ['nullable', 'array', 'max:3'] )
            ->and( $rules['*'] )->toBe( ['array'] )
            ->and( $rules['*.name'] )->toBe( ['nullable', 'string', 'max:255'] )
            ->and( $rules['*.email'] )->toBe( ['nullable', 'string', 'max:255', 'email'] )
            ->and( $rules['*.role'] )->toBe( ['nullable', 'string', 'in:"Speaker","Guest"'] )
            ->and( $rules['*.age'] )->toBe( ['nullable', 'numeric'] );
    } );

    it( 'rejects invalid cells on the row the visitor sees', function (): void {
        ( $this->submitApi )( [
            ['name' => 'Ada', 'email' => 'ada@example.com'],
            ['name' => '', 'email' => ''],
            ['name' => 'Bad', 'email' => 'not-an-email', 'role' => 'Host', 'age' => 'old'],
        ] )->assertUnprocessable()->assertJsonValidationErrors( [
            'data.attendees.2.email' => 'Attendees: Email must be a valid email address.',
            'data.attendees.2.role'  => 'Attendees: please choose a valid Role.',
            'data.attendees.2.age'   => 'Attendees: Age must be a number.',
        ] );
    } );

    it( 'requires a filled row for a required list and the minimum once any row is filled', function (): void {
        $this->attendees->update( ['is_required' => true, 'field_config' => array_merge( $this->attendees->field_config, ['min_rows' => 2] )] );

        ( $this->submitApi )( [['name' => '', 'email' => '']] )
            ->assertUnprocessable()->assertJsonValidationErrors( ['data.attendees' => 'Please fill in at least one row of Attendees.'] );

        ( $this->submitApi )( [['name' => 'Ada'], ['name' => '']] )
            ->assertUnprocessable()->assertJsonValidationErrors( ['data.attendees' => 'Please fill in at least 2 rows of Attendees.'] );
    } );

    it( 'allows an optional list to be left empty', function (): void {
        $this->attendees->update( ['field_config' => array_merge( $this->attendees->field_config, ['min_rows' => 2] )] );

        ( $this->submitApi )( [['name' => '']] )->assertCreated();
    } );

    it( 'rejects more rows than the maximum', function (): void {
        ( $this->submitApi )( array_fill( 0, 4, ['name' => 'Ada'] ) )
            ->assertUnprocessable()->assertJsonValidationErrors( ['data.attendees' => 'Attendees may have no more than 3 rows.'] );
    } );

    it( 'stores the filled rows numbered from 0', function (): void {
        ( $this->submitApi )( [
            ['name' => ''],
            ['name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'Speaker'],
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->first()->value_array )->toBe( [
            ['name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'Speaker', 'age' => ''],
        ] );
    } );
} );

describe( 'conditional logic', function (): void {
    it( 'treats a list of blank rows as empty', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'type'              => 'text',
            'name'              => 'why_none',
            'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'attendees', 'operator' => 'is_empty']]],
        ] );

        $fields  = $this->form->fields()->get();
        $service = app( ConditionalLogicService::class );

        expect( $service->getHiddenFields( $fields, ['attendees' => [['name' => '', 'email' => ' ']]] )['why_none'] )->toBeFalse()
            ->and( $service->getHiddenFields( $fields, ['attendees' => [['name' => 'Ada']]] )['why_none'] )->toBeTrue();
    } );

    it( 'works hidden fields out again when a row is removed in Livewire', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'type'              => 'text',
            'name'              => 'why_none',
            'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'attendees', 'operator' => 'is_empty']]],
        ] );

        // hiddenFields is locked, so the server must recalculate it itself
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formData.attendees.0.name', 'Ada' )
            ->assertSet( 'hiddenFields.why_none', true )
            ->call( 'removeListRow', 'attendees', 0 )
            ->assertSet( 'hiddenFields.why_none', false );
    } );
} );

describe( 'Livewire', function (): void {
    it( 'adds and removes rows, up to the maximum', function (): void {
        $component = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->assertSet( 'formData.attendees', [['name' => '', 'email' => '', 'role' => '', 'age' => '']] )
            ->set( 'formData.attendees.0.name', 'Ada' )
            ->call( 'addListRow', 'attendees' )
            ->assertDispatched( 'forms-list-row-added', field: 'attendees', index: 1 )
            ->call( 'addListRow', 'attendees' )
            ->call( 'addListRow', 'attendees' );

        expect( $component->get( 'formData.attendees' ) )->toHaveCount( 3 );

        $component->call( 'removeListRow', 'attendees', 0 )
            ->assertDispatched( 'forms-list-row-removed', field: 'attendees', index: 0 );

        expect( $component->get( 'formData.attendees' ) )->toHaveCount( 2 )
            ->and( $component->get( 'formData.attendees.0.name' ) )->toBe( '' );

        $component->call( 'removeListRow', 'attendees', 1 )->call( 'removeListRow', 'attendees', 0 );

        expect( $component->get( 'formData.attendees' ) )->toBe( [['name' => '', 'email' => '', 'role' => '', 'age' => '']] );
    } );

    it( 'ignores rows for fields that are not lists', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'title'] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->call( 'addListRow', 'title' )
            ->assertNotDispatched( 'forms-list-row-added' )
            ->assertSet( 'formData.title', '' );
    } );

    it( 'validates and stores the rows', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.attendees.0', ['name' => 'Ada', 'email' => 'bad', 'role' => '', 'age' => ''] )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.attendees.0.email' => 'email'] )
            ->assertSee( 'Attendees: Email must be a valid email address.' )
            ->set( 'formData.attendees.0.email', 'ada@example.com' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value_array[0]['email'] )->toBe( 'ada@example.com' );
    } );
} );

describe( 'display and export', function (): void {
    beforeEach( function (): void {
        ( $this->submitApi )( [
            ['name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'Speaker'],
            ['name' => 'Grace <b>', 'role' => 'Guest', 'age' => '85'],
        ] )->assertCreated();

        $this->submission = $this->form->submissions()->first();
        $this->value      = $this->submission->values->first();
    } );

    it( 'shows one row per line and an escaped table in HTML', function (): void {
        expect( $this->value->display_value )->toBe( "Ada, ada@example.com, Speaker\nGrace <b>, Guest, 85" )
            ->and( $this->value->display_html )->toContain( '<table' )
            ->toContain( '<th scope="col">Email</th>' )
            ->toContain( '<td>Grace &lt;b&gt;</td>' )
            ->not->toContain( '<td>Grace <b>' )
            ->and( $this->value->getRawValue() )->toBe( 'Ada, ada@example.com, Speaker; Grace <b>, Guest, 85' );
    } );

    it( 'exports one row per line or JSON', function (): void {
        $fields = $this->form->fields()->get();
        $row    = app( ExportService::class )->buildRow( $this->form, $this->submission->fresh( ['values'] ), $fields );

        expect( implode( '|', $row ) )->toContain( "Ada, ada@example.com, Speaker\nGrace <b>, Guest, 85" );

        $this->attendees->update( ['field_config' => array_merge( $this->attendees->field_config, ['export_format' => 'json'] )] );
        $fields = $this->form->fields()->get();
        $row    = app( ExportService::class )->buildRow( $this->form, $this->submission->fresh( ['values'] ), $fields );

        expect( implode( '|', $row ) )->toContain( '[{"Name":"Ada","Email":"ada@example.com","Role":"Speaker","Age":""},{"Name":"Grace <b>"' );
    } );

    it( 'keeps the rows keyed by column for webhooks and the JSON export', function (): void {
        expect( $this->value->getStructuredValue() )->toBe( [
            ['name' => 'Ada', 'email' => 'ada@example.com', 'role' => 'Speaker', 'age' => ''],
            ['name' => 'Grace <b>', 'email' => '', 'role' => 'Guest', 'age' => '85'],
        ] );
    } );

    it( 'uses the table in HTML notifications', function (): void {
        expect( app( NotificationService::class )->formatAllFieldsAsTable( $this->submission->fresh( ['values'] ) ) )->toContain( '<th scope="col">Role</th>' );
    } );
} );

describe( 'rendering', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
    } );

    it( 'renders labelled rows with add and remove buttons', function (): void {
        $html = FieldRenderer::render( $this->attendees, [['name' => 'Ada', 'email' => '', 'role' => '', 'age' => ''], ['name' => '', 'email' => '', 'role' => '', 'age' => '']] );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( '<legend' )
            ->toContain( 'role="group"' )
            ->toContain( '>Attendee 2</span>' )
            ->toContain( 'id="field-attendees-1-email"' )
            ->toContain( 'type="email"' )
            ->toContain( 'wire:model="formData.attendees.1.email"' )
            ->toContain( 'aria-label="Remove Attendee 2"' )
            ->toContain( 'Add Attendee' )
            ->toContain( "addListRow('attendees')" )
            ->toContain( 'aria-live="polite"' )
            ->toContain( '<option value="Speaker">Speaker</option>' );
    } );

    it( 'disables the add button at the maximum', function (): void {
        $row = ['name' => 'Ada', 'email' => '', 'role' => '', 'age' => ''];

        expect( FieldRenderer::render( $this->attendees, [$row, $row, $row] ) )->toContain( 'disabled' )
            ->toContain( 'Up to 3 rows.' );
    } );

    it( 'sends the columns and limits to React and Vue', function (): void {
        $resource = ( new FormFieldResource( $this->attendees ) )->toArray( Request::create( '/' ) );

        expect( $resource['list'] )->toMatchArray( ['min_rows' => null, 'max_rows' => 3, 'row_label' => 'Attendee'] )
            ->and( array_column( $resource['list']['columns'], 'key' ) )->toBe( ['name', 'email', 'role', 'age'] );
    } );
} );
