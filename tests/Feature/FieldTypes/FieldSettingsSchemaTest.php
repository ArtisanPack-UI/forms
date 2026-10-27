<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Http\Resources\FormStepResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\Models\User;

describe( 'FieldSettings schema', function (): void {
    it( 'only lets legacy_store name a settings column', function (): void {
        $schema = FieldSettings::normalizeSchema( [
            ['key' => 'a', 'legacy_store' => 'validation_rules'],
            ['key' => 'b', 'legacy_store' => 'name'],
            ['key' => 'c', 'legacy_store' => 'conditional_logic'],
        ] );

        expect( $schema[0]['legacy_store'] )->toBe( 'validation_rules' )
            ->and( $schema[1] )->not->toHaveKey( 'legacy_store' )
            ->and( $schema[2] )->not->toHaveKey( 'legacy_store' );
    } );

    it( 'fills in defaults and drops invalid entries', function (): void {
        $schema = FieldSettings::normalizeSchema( [
            ['key' => 'colour'],
            ['key'   => 'size', 'input' => 'select', 'options' => ['s' => 'Small', ['value' => 'l', 'label' => 'Large']]],
            ['key'   => 'odd', 'input' => 'slider', 'store' => 'elsewhere', 'tab' => 'secret'],
            ['label' => 'No key'],
            'not an array',
        ] );

        expect( $schema )->toHaveCount( 3 )
            ->and( $schema[0] )->toMatchArray( ['key' => 'colour', 'label' => 'colour', 'input' => 'text', 'store' => 'field_config', 'tab' => 'general'] )
            ->and( $schema[1]['options'] )->toBe( [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']] )
            ->and( $schema[2] )->toMatchArray( ['input' => 'text', 'store' => 'field_config', 'tab' => 'general'] );
    } );

    it( 'gives every built-in setting a key, label, input, store and tab', function (): void {
        foreach ( array_keys( FieldTypes::HANDLERS ) as $type ) {
            foreach ( FieldTypes::getTypeConfig( $type )['settings'] as $setting ) {
                expect( $setting['key'] )->toBeString()->not->toBeEmpty()
                    ->and( $setting['label'] )->toBeString()->not->toBeEmpty()
                    ->and( FieldSettings::INPUTS )->toContain( $setting['input'] )
                    ->and( FieldSettings::STORES )->toContain( $setting['store'] )
                    ->and( FieldSettings::TABS )->toContain( $setting['tab'] );
            }
        }
    } );

    it( 'parses raw input by setting type', function ( array $setting, mixed $raw, mixed $expected ): void {
        $setting = FieldSettings::normalizeSchema( [array_merge( ['key' => 'x'], $setting )] )[0];

        expect( FieldSettings::parse( $setting, $raw ) )->toBe( $expected );
    } )->with( [
        'integer'                => [['input' => 'integer'], ' 12 ', 12],
        'integer, decimal'       => [['input' => 'integer'], '2.5', null],
        'integer, below min'     => [['input' => 'integer', 'min' => 1], '0', null],
        'integer, above max'     => [['input' => 'integer', 'max' => 10], '11', null],
        'integer, zero allowed'  => [['input' => 'integer', 'min' => 0], '0', 0],
        'number, whole'          => [['input' => 'number'], '10', 10],
        'number, decimal'        => [['input' => 'number'], '0.5', 0.5],
        'number, negative'       => [['input' => 'number'], '-3', -3],
        'number, not numeric'    => [['input' => 'number'], 'abc', null],
        'text, trimmed'          => [['input' => 'text'], '  Agreed  ', 'Agreed'],
        'text, max length'       => [['input' => 'text', 'max_length' => 3], 'abcdef', 'abc'],
        'text, blank'            => [['input' => 'text'], '   ', null],
        'select, known'          => [['input' => 'select', 'options' => [['value' => 'a', 'label' => 'A']]], 'a', 'a'],
        'select, unknown'        => [['input' => 'select', 'options' => [['value' => 'a', 'label' => 'A']]], 'b', null],
        'toggle, on'             => [['input' => 'toggle'], true, true],
        'toggle, on as string'   => [['input' => 'toggle'], 'true', true],
        'toggle, off clears'     => [['input' => 'toggle'], false, null],
        'date'                   => [['input' => 'date'], '2026-02-28', '2026-02-28'],
        'date, impossible'       => [['input' => 'date'], '2026-02-30', null],
        'time, seconds dropped'  => [['input' => 'time'], '09:30:00', '09:30'],
        'time, invalid'          => [['input' => 'time'], '25:00', null],
        'list'                   => [['input' => 'list'], ' pdf, ,docx ', ['pdf', 'docx']],
        'list, only commas'      => [['input' => 'list'], ' , ', null],
        'list, array'            => [['input' => 'list'], ['pdf', ' '], ['pdf']],
        'array for a text input' => [['input' => 'text'], ['x'], null],
    ] );

    it( 'shows a stored value as the builder input expects', function (): void {
        $field = FormField::factory()->create( [
            'type'             => 'file',
            'field_config'     => ['allowed_types' => ['pdf', 'png'], 'max_size' => 100],
            'validation_rules' => ['max_size' => 4000],
        ] );

        expect( FieldSettings::inputValue( $field, FieldSettings::find( $field, 'max_size' ) ) )->toBe( '4000' )
            ->and( FieldSettings::inputValue( $field, FieldSettings::find( $field, 'allowed_types' ) ) )->toBe( 'pdf, png' );

        $heading = FormField::factory()->create( ['type' => 'heading', 'field_config' => []] );
        $rating  = FormField::factory()->create( ['type' => 'rating', 'field_config' => ['allow_half' => true]] );

        expect( FieldSettings::inputValue( $heading, FieldSettings::find( $heading, 'level' ) ) )->toBe( 'h3' )
            ->and( FieldSettings::inputValue( $rating, FieldSettings::find( $rating, 'allow_half' ) ) )->toBeTrue()
            ->and( FieldSettings::inputValue( $rating, FieldSettings::find( $rating, 'read_only' ) ) )->toBeFalse()
            ->and( FieldSettings::inputValue( $rating, FieldSettings::find( $rating, 'max_rating' ) ) )->toBe( '' );
    } );

    it( 'stores a setting in its column and drops the legacy copy', function (): void {
        $field = FormField::factory()->create( [
            'type'             => 'file',
            'field_config'     => ['max_size' => 5120],
            'validation_rules' => ['max_size' => 4000, 'min' => 1],
        ] );

        expect( FieldSettings::update( $field, FieldSettings::find( $field, 'max_size' ), '100' ) )->toBe( [
            'field_config'     => ['max_size' => 100],
            'validation_rules' => ['min' => 1],
        ] );
    } );

    it( 'finds only the settings a type declares', function (): void {
        $text = FormField::factory()->create( ['type' => 'text'] );

        expect( FieldSettings::find( $text, 'pattern' ) )->not->toBeNull()
            ->and( FieldSettings::find( $text, 'rows' ) )->toBeNull()
            ->and( array_column( FieldSettings::forTab( $text, 'general' ), 'key' ) )->toBe( ['input_mask', 'custom_mask', 'show_counter', 'label_placement', 'description_placement'] )
            ->and( array_column( FieldSettings::forTab( $text, 'validation' ), 'key' ) )->toBe( ['min', 'max', 'pattern', 'no_duplicates', 'error_message', 'required_message'] )
            ->and( array_column( FieldSettings::forTab( $text, 'advanced' ), 'key' ) )->toBe( ['read_only', 'autocomplete', 'admin_label', 'dynamic_population', 'dynamic_parameter', 'visibility', 'do_not_store', 'mask_in_admin', 'redact_after_days'] );
    } );
} );

describe( 'settings schema in the API', function (): void {
    it( 'includes the schema and read-only state in the field resource', function (): void {
        $field = FormField::factory()->create( ['type' => 'textarea', 'field_config' => ['read_only' => true]] );

        $data = ( new FormFieldResource( $field ) )->toArray( Request::create( '/' ) );

        expect( $data['is_read_only'] )->toBeTrue()
            ->and( $data['settings_schema'] )->toBe( FieldSettings::schema( $field ) )
            ->and( array_column( $data['settings_schema'], 'key' ) )->toContain( 'rows', 'min', 'max', 'read_only' );
    } );

    it( 'leaves the schema out of the public render payload', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'text'] );

        $payload = json_decode( ( new FormRenderResource( $form->load( 'fields', 'steps' ) ) )->toJson(), true );

        expect( $payload['fields'][0] )->not->toHaveKey( 'settings_schema' )
            ->and( $payload['fields'][0] )->toHaveKey( 'is_read_only' );
    } );

    it( 'leaves the schema out of the fields of a multi-step render payload', function (): void {
        $form = Form::factory()->create( ['is_multi_step' => true] );
        $step = FormStep::factory()->for( $form )->create();
        FormField::factory()->for( $form )->create( ['type' => 'text', 'step_id' => $step->id] );

        $payload = json_decode( ( new FormRenderResource( $form->load( 'fields', 'steps.fields' ) ) )->toJson(), true );

        expect( $payload['steps'][0]['fields'] )->toHaveCount( 1 )
            ->and( $payload['steps'][0]['fields'][0] )->not->toHaveKey( 'settings_schema' )
            ->and( $payload['steps'][0]['fields'][0] )->toHaveKey( 'is_read_only' );
    } );

    it( 'keeps the schema in the fields of a step for the builder', function (): void {
        $form = Form::factory()->create( ['is_multi_step' => true] );
        $step = FormStep::factory()->for( $form )->create();
        FormField::factory()->for( $form )->create( ['type' => 'text', 'step_id' => $step->id] );

        $payload = json_decode( ( new FormStepResource( $step->load( 'fields' ) ) )->toJson(), true );

        expect( $payload['fields'][0] )->toHaveKey( 'settings_schema' );
    } );
} );

describe( 'Livewire builder settings from the schema', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
    } );

    it( 'renders one control per setting in the right tab', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'rating', 'field_config' => ['max_rating' => 7]] );

        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->assertSeeHtml( 'id="field-setting-max_rating"' )
            ->assertSeeHtml( 'value="7"' )
            ->assertSeeHtml( 'max="10"' )
            ->assertSeeHtml( "updateSetting('max_rating', \$event.target.value)" )
            ->assertSeeHtml( "updateSetting('allow_half', \$event.target.checked)" )
            ->assertSeeHtml( "updateSetting('size', \$event.target.value)" )
            ->assertSeeHtml( "updateSetting('read_only', \$event.target.checked)" )
            ->assertSeeHtml( 'aria-describedby="field-setting-max_rating-hint"' )
            ->assertSee( 'Max Rating' );
    } );

    it( 'saves rating settings', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'rating', 'field_config' => ['max_rating' => 5]] );

        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateSetting', 'max_rating', '10' )
            ->call( 'updateSetting', 'allow_half', true )
            ->call( 'updateSetting', 'size', 'lg' )
            ->call( 'updateSetting', 'read_only', true );

        expect( $field->fresh()->field_config )->toBe( [
            'max_rating' => 10,
            'allow_half' => true,
            'size'       => 'lg',
            'read_only'  => true,
        ] );
    } );

    it( 'clears a toggle when it is switched off', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'field_config' => ['read_only' => true]] );

        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateSetting', 'read_only', false );

        expect( $field->fresh()->field_config )->toBe( [] );
    } );

    it( 'rejects a max rating outside 1 to 10', function ( string $input ): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'rating', 'field_config' => ['max_rating' => 5]] );

        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateSetting', 'max_rating', $input );

        expect( $field->fresh()->field_config )->not->toHaveKey( 'max_rating' );
    } )->with( ['0', '11', 'five'] );

    it( 'renders settings for a custom handler type', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['effort_score'] = [
                'label'    => 'Customer Effort Score',
                'category' => 'advanced',
                'settings' => [
                    ['key' => 'low_label', 'label' => 'Low end label', 'placeholder' => 'Not likely'],
                ],
            ];

            return $types;
        } );

        $field = FormField::factory()->for( $this->form )->create( ['type' => 'effort_score'] );

        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->assertSee( 'Low end label' )
            ->assertSeeHtml( 'placeholder="Not likely"' )
            ->call( 'updateSetting', 'low_label', 'Never' );

        expect( $field->fresh()->field_config )->toBe( ['low_label' => 'Never'] );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );
} );
