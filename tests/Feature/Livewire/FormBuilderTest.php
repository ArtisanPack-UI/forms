<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Livewire\Livewire;

// Filters are process-global, so clear the ones these tests register here rather
// than at the end of each test: an assertion or a render that throws must still
// not leak a registration into a later test.
afterEach( function (): void {
    removeAllFilters( 'ap.forms.fieldTypes' );
    removeAllFilters( 'ap.forms.fieldCategories' );
    removeAllFilters( 'ap.forms.fieldSettings' );
    removeAllFilters( 'ap.forms.fieldCardPreview' );
} );

describe( 'FormBuilder Component', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
    } );

    describe( 'rendering', function (): void {
        it( 'renders the form builder component', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertStatus( 200 )
                ->assertSee( 'Add Fields' )
                ->assertSee( 'Form Fields' );
        } );

        it( 'shows field categories in the palette', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSee( 'Basic Fields' )
                ->assertSee( 'Choice Fields' )
                ->assertSee( 'Advanced Fields' );
        } );

        it( 'shows existing fields on load', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['label' => 'Test Field'] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSee( 'Test Field' );
        } );

        it( 'shows a custom type filed under a new category in the palette', function (): void {
            addFilter( 'ap.forms.fieldTypes', static function ( array $types ): array {
                $types['booking_slot'] = [
                    'label'                  => 'Booking Slot',
                    'icon'                   => 'o-calendar-days',
                    'category'               => 'bookings',
                    'has_options'            => false,
                    'supports_placeholder'   => false,
                    'supports_default_value' => false,
                    'validation_options'     => [],
                ];

                return $types;
            } );

            addFilter( 'ap.forms.fieldCategories', static function ( array $categories ): array {
                $categories['bookings'] = [
                    'label'  => 'Bookings',
                    'fields' => ['booking_slot'],
                ];

                return $categories;
            } );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSee( 'Bookings' )
                ->assertSee( 'Booking Slot' );
        } );

        it( 'renders custom field settings from the ap.forms.fieldSettings filter', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'label' => 'Contact'] );

            addFilter(
                'ap.forms.fieldSettings',
                static fn ( string $html, $f, $form ): string => $html . '<div>CUSTOM-SETTINGS-MARKER-' . $f->type . '</div>',
            );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSee( 'CUSTOM-SETTINGS-MARKER-text', false );
        } );

        it( 'renders a live card preview from the ap.forms.fieldCardPreview filter', function (): void {
            FormField::factory()->for( $this->form )->create( ['type' => 'text', 'label' => 'Contact'] );

            addFilter(
                'ap.forms.fieldCardPreview',
                static fn ( string $html, $f ): string => $html . '<div>CARD-PREVIEW-MARKER-' . $f->type . '</div>',
            );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSee( 'CARD-PREVIEW-MARKER-text', false );
        } );
    } );

    describe( 'adding fields', function (): void {
        it( 'adds a text field when clicking the button', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'text' )
                ->assertDispatched( 'field-added' );

            expect( $this->form->fields()->count() )->toBe( 1 )
                ->and( $this->form->fields()->first()->type )->toBe( 'text' );
        } );

        it( 'adds an email field with correct defaults', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'email' );

            $field = $this->form->fields()->first();

            expect( $field->type )->toBe( 'email' )
                ->and( $field->label )->toBe( 'Email Address' );
        } );

        it( 'selects the newly added field', function (): void {
            $component = Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'text' );

            $field = $this->form->fields()->first();

            $component->assertSet( 'selectedFieldId', $field->uuid );
        } );

        it( 'marks form as dirty when adding a field', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSet( 'isDirty', false )
                ->call( 'addField', 'text' )
                ->assertSet( 'isDirty', true );
        } );
    } );

    describe( 'selecting fields', function (): void {
        it( 'selects a field when clicked', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSet( 'selectedFieldId', $field->uuid );
        } );

        it( 'deselects the current field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSet( 'selectedFieldId', $field->uuid )
                ->call( 'deselectField' )
                ->assertSet( 'selectedFieldId', null );
        } );
    } );

    describe( 'updating fields', function (): void {
        it( 'updates the selected field', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['label' => 'Original'] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'updateField', ['label' => 'Updated Label'] );

            expect( $field->fresh()->label )->toBe( 'Updated Label' );
        } );

        it( 'marks form as dirty when updating a field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSet( 'isDirty', false )
                ->call( 'updateField', ['label' => 'New Label'] )
                ->assertSet( 'isDirty', true );
        } );
    } );

    describe( 'field storage contract (issues #79, #82)', function (): void {
        it( 'saves file settings to field_config and enforces them', function (): void {
            $field = FormField::factory()->for( $this->form )->file()->create( [
                'field_config'     => ['max_size' => 5120, 'allowed_types' => ['pdf', 'png']],
                'validation_rules' => ['max_size' => 4000, 'allowed_types' => ['png'], 'min' => 1],
            ] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'updateFileSetting', 'max_size', '100' )
                ->call( 'updateFileSetting', 'allowed_types', ' pdf, ,docx ' )
                ->assertSet( 'isDirty', true );

            $field->refresh();

            expect( $field->field_config )->toBe( ['max_size' => 100, 'allowed_types' => ['pdf', 'docx']] )
                ->and( $field->validation_rules )->toBe( ['min' => 1] )
                ->and( $field->buildValidationRules() )->toContain( 'mimes:pdf,docx', 'max:100' );
        } );

        it( 'clears a file setting when the input is blanked', function ( string $setting, string $value ): void {
            $field = FormField::factory()->for( $this->form )->file()->create( [
                'field_config' => ['max_size' => 100, 'allowed_types' => ['pdf']],
            ] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'updateFileSetting', $setting, $value );

            expect( $field->fresh()->field_config )->not->toHaveKey( $setting );
        } )->with( [
            'max size, blank'            => ['max_size', ''],
            'max size, zero'             => ['max_size', '0'],
            'allowed types, only commas' => ['allowed_types', ' , '],
        ] );

        it( 'ignores unknown file settings', function (): void {
            $field = FormField::factory()->for( $this->form )->file()->create( ['field_config' => ['max_size' => 100]] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'updateFileSetting', 'options', 'x' )
                ->assertSet( 'isDirty', false );

            expect( $field->fresh()->field_config )->toBe( ['max_size' => 100] );
        } );

        it( 'parses numeric validation rules on the server', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'type'             => 'number',
                'validation_rules' => ['min' => 1, 'pattern' => '/x/'],
            ] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'updateValidationRule', 'max', '10' )
                ->call( 'updateValidationRule', 'step', '0.5' )
                ->call( 'updateValidationRule', 'min', '' )
                ->call( 'updateValidationRule', 'pattern', 'ignored' );

            expect( $field->fresh()->validation_rules )->toBe( ['pattern' => '/x/', 'max' => 10, 'step' => 0.5] );
        } );

        it( 'renders editor inputs without bare JS globals that Livewire 4 would rewrite', function (): void {
            $field = FormField::factory()->for( $this->form )->file()->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSeeHtml( "updateFileSetting('max_size', \$event.target.value)" )
                ->assertSeeHtml( "updateFileSetting('allowed_types', \$event.target.value)" )
                ->assertDontSeeHtml( 'parseInt(' )
                ->assertDontSeeHtml( 'parseFloat(' )
                ->assertDontSeeHtml( '.map(t =>' );
        } );

        it( 'saves paragraph and html content to field_config.content', function ( string $type ): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'type'          => $type,
                'default_value' => 'Legacy content',
                'field_config'  => ['level' => 'keep'],
            ] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSeeHtml( 'id="field-content"' )
                ->assertSee( 'Legacy content' )
                ->assertDontSeeHtml( 'id="field-help-text"' )
                ->call( 'updateLayoutContent', 'New content' );

            $field->refresh();

            expect( $field->field_config )->toBe( ['level' => 'keep', 'content' => 'New content'] )
                ->and( $field->default_value )->toBeNull()
                ->and( $field->getLayoutContent() )->toBe( 'New content' );
        } )->with( ['paragraph', 'html'] );

        it( 'saves heading text to the label and clears legacy content', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'type'          => 'heading',
                'label'         => 'Section Heading',
                'default_value' => 'Legacy heading',
            ] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->assertSee( 'Heading Text' )
                ->assertSeeHtml( 'value="Legacy heading"' )
                ->call( 'updateLayoutContent', 'New heading' );

            $field->refresh();

            expect( $field->label )->toBe( 'New heading' )
                ->and( $field->default_value )->toBeNull();
        } );

        it( 'ignores layout content updates for other field types', function (): void {
            $field = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'label' => 'Name'] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'updateLayoutContent', 'Changed' );

            expect( $field->fresh()->label )->toBe( 'Name' );
        } );

        it( 'does nothing when no field is selected', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'updateFileSetting', 'max_size', '100' )
                ->call( 'updateValidationRule', 'min', '1' )
                ->call( 'updateLayoutContent', 'x' )
                ->assertSet( 'isDirty', false );
        } );
    } );

    describe( 'deleting fields', function (): void {
        it( 'deletes a field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'deleteField', $field->uuid )
                ->assertDispatched( 'field-deleted' );

            expect( $this->form->fields()->count() )->toBe( 0 );
        } );

        it( 'clears selection when deleting the selected field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'selectField', $field->uuid )
                ->call( 'deleteField', $field->uuid )
                ->assertSet( 'selectedFieldId', null );
        } );
    } );

    describe( 'duplicating fields', function (): void {
        it( 'duplicates a field', function (): void {
            $field = FormField::factory()->for( $this->form )->create( [
                'label' => 'Original',
                'type'  => 'text',
            ] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'duplicateField', $field->uuid )
                ->assertDispatched( 'field-duplicated' );

            expect( $this->form->fields()->count() )->toBe( 2 );

            $duplicate = $this->form->fields()->where( 'id', '!=', $field->id )->first();
            expect( $duplicate->label )->toBe( 'Original (Copy)' )
                ->and( $duplicate->type )->toBe( 'text' );
        } );

        it( 'selects the duplicated field', function (): void {
            $field = FormField::factory()->for( $this->form )->create();

            $component = Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'duplicateField', $field->uuid );

            $duplicate = $this->form->fields()->where( 'id', '!=', $field->id )->first();
            $component->assertSet( 'selectedFieldId', $duplicate->uuid );
        } );
    } );

    describe( 'reordering fields', function (): void {
        it( 'reorders fields based on UUID array', function (): void {
            $field1 = FormField::factory()->for( $this->form )->create( ['sort_order' => 1] );
            $field2 = FormField::factory()->for( $this->form )->create( ['sort_order' => 2] );
            $field3 = FormField::factory()->for( $this->form )->create( ['sort_order' => 3] );

            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->dispatch( 'fields-reordered', orderedUuids: [$field3->uuid, $field2->uuid, $field1->uuid] );

            expect( $field1->fresh()->sort_order )->toBe( 3 )
                ->and( $field2->fresh()->sort_order )->toBe( 2 )
                ->and( $field3->fresh()->sort_order )->toBe( 1 );
        } );
    } );

    describe( 'saving', function (): void {
        it( 'clears dirty state after saving', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->call( 'addField', 'text' )
                ->assertSet( 'isDirty', true )
                ->call( 'saveForm' )
                ->assertSet( 'isDirty', false )
                ->assertDispatched( 'form-saved' );
        } );

        it( 'does not save when not dirty', function (): void {
            Livewire::test( FormBuilder::class, ['form' => $this->form] )
                ->assertSet( 'isDirty', false )
                ->call( 'saveForm' )
                ->assertNotDispatched( 'form-saved' );
        } );
    } );
} );

describe( 'FormBuilder Multi-Step', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->multiStep()->create();
        $this->step = FormStep::factory()->for( $this->form )->create();
    } );

    it( 'shows step manager for multi-step forms', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->assertSee( $this->step->title )
            ->assertSee( 'Add Step' );
    } );

    it( 'sets active step on mount', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->assertSet( 'activeStepId', $this->step->id );
    } );

    it( 'adds a new step', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'addStep' )
            ->assertDispatched( 'step-added' );

        expect( $this->form->steps()->count() )->toBe( 2 );
    } );

    it( 'selects a step', function (): void {
        $step2 = FormStep::factory()->for( $this->form )->create();

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectStep', $step2->id )
            ->assertSet( 'activeStepId', $step2->id );
    } );

    it( 'updates a step', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'updateStep', $this->step->id, ['title' => 'Updated Title'] );

        expect( $this->step->fresh()->title )->toBe( 'Updated Title' );
    } );

    it( 'deletes a step when more than one exists', function (): void {
        $step2 = FormStep::factory()->for( $this->form )->create();

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'deleteStep', $step2->id )
            ->assertDispatched( 'step-deleted' );

        expect( $this->form->steps()->count() )->toBe( 1 );
    } );

    it( 'adds fields to the active step', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'addField', 'text' );

        $field = $this->form->fields()->first();

        expect( $field->step_id )->toBe( $this->step->id );
    } );

    it( 'reorders steps based on ID array', function (): void {
        $step2 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 2] );
        $step3 = FormStep::factory()->for( $this->form )->create( ['sort_order' => 3] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->dispatch( 'steps-reordered', orderedIds: [$step3->id, $step2->id, $this->step->id] );

        expect( $this->step->fresh()->sort_order )->toBe( 3 )
            ->and( $step2->fresh()->sort_order )->toBe( 2 )
            ->and( $step3->fresh()->sort_order )->toBe( 1 );
    } );
} );

describe( 'FormBuilder Enable/Disable Multi-Step', function (): void {
    it( 'enables multi-step mode on a single-step form', function (): void {
        $form  = Form::factory()->create( ['is_multi_step' => false] );
        $field = FormField::factory()->for( $form )->create( ['step_id' => null] );

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'enableMultiStep' )
            ->assertDispatched( 'multi-step-enabled' );

        $form->refresh();
        $field->refresh();

        expect( $form->is_multi_step )->toBeTrue()
            ->and( $form->steps()->count() )->toBe( 1 )
            ->and( $field->step_id )->not->toBeNull();
    } );

    it( 'does nothing when enabling multi-step on already multi-step form', function (): void {
        $form = Form::factory()->multiStep()->create();
        FormStep::factory()->for( $form )->create();

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'enableMultiStep' )
            ->assertNotDispatched( 'multi-step-enabled' );

        expect( $form->steps()->count() )->toBe( 1 );
    } );

    it( 'disables multi-step mode', function (): void {
        $form  = Form::factory()->multiStep()->create();
        $step  = FormStep::factory()->for( $form )->create();
        $field = FormField::factory()->for( $form )->create( ['step_id' => $step->id] );

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'disableMultiStep' )
            ->assertDispatched( 'multi-step-disabled' )
            ->assertSet( 'activeStepId', null );

        $form->refresh();
        $field->refresh();

        expect( $form->is_multi_step )->toBeFalse()
            ->and( $form->steps()->count() )->toBe( 0 )
            ->and( $field->step_id )->toBeNull();
    } );

    it( 'does nothing when disabling multi-step on single-step form', function (): void {
        $form = Form::factory()->create( ['is_multi_step' => false] );

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'disableMultiStep' )
            ->assertNotDispatched( 'multi-step-disabled' );
    } );

    it( 'sets active step when enabling multi-step', function (): void {
        $form = Form::factory()->create( ['is_multi_step' => false] );

        $component = Livewire::test( FormBuilder::class, ['form' => $form] )
            ->call( 'enableMultiStep' );

        $form->refresh();
        $step = $form->steps()->first();

        $component->assertSet( 'activeStepId', $step->id );
    } );
} );

describe( 'FormBuilder display settings (issue #83)', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
    } );

    it( 'saves a display setting to field_config', function ( string $type, string $setting, string $input, int|string $stored ): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => $type,
            'field_config' => ['keep' => 'me'],
        ] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateFieldConfig', $setting, $input )
            ->assertSet( 'isDirty', true );

        expect( $field->fresh()->field_config )->toBe( ['keep' => 'me', $setting => $stored] );
    } )->with( [
        'textarea rows'           => ['textarea', 'rows', '8', 8],
        'heading level'           => ['heading', 'level', 'h2', 'h2'],
        'divider style'           => ['divider', 'style', 'dashed', 'dashed'],
        'divider spacing'         => ['divider', 'spacing', 'large', 'large'],
        'time step'               => ['time', 'step', '1', 1],
        'checkbox checked text'   => ['checkbox', 'checked_label', ' Agreed ', 'Agreed'],
        'checkbox unchecked text' => ['checkbox', 'unchecked_label', 'Declined', 'Declined'],
    ] );

    it( 'clears a display setting when the input is blank or invalid', function ( string $type, string $setting, string $input ): void {
        $field = FormField::factory()->for( $this->form )->create( [
            'type'         => $type,
            'field_config' => [$setting => 'old'],
        ] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateFieldConfig', $setting, $input );

        expect( $field->fresh()->field_config )->not->toHaveKey( $setting );
    } )->with( [
        'rows, blank'         => ['textarea', 'rows', ''],
        'rows, zero'          => ['textarea', 'rows', '0'],
        'rows, too many'      => ['textarea', 'rows', '51'],
        'rows, decimal'       => ['textarea', 'rows', '2.5'],
        'level, h1'           => ['heading', 'level', 'h1'],
        'style, unknown'      => ['divider', 'style', 'double'],
        'spacing, unknown'    => ['divider', 'spacing', 'huge'],
        'step, negative'      => ['time', 'step', '-5'],
        'checked text, blank' => ['checkbox', 'checked_label', '   '],
    ] );

    it( 'ignores settings that do not apply to the field type', function ( string $type, string $setting ): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => $type, 'field_config' => []] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateFieldConfig', $setting, '5' )
            ->assertSet( 'isDirty', false );

        expect( $field->fresh()->field_config )->toBe( [] );
    } )->with( [
        'rows on a text field'      => ['text', 'rows'],
        'step on a number field'    => ['number', 'step'],
        'options on a textarea'     => ['textarea', 'options'],
        'level on a divider'        => ['divider', 'level'],
    ] );

    it( 'saves and clears time limits', function (): void {
        $field = FormField::factory()->for( $this->form )->time()->create( [
            'validation_rules' => ['min_time' => '08:00', 'max_time' => '18:00'],
        ] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateTimeLimit', 'min_time', '09:00:00' )
            ->call( 'updateTimeLimit', 'max_time', '' )
            ->call( 'updateTimeLimit', 'pattern', '12:00' );

        expect( $field->fresh()->validation_rules )->toBe( ['min_time' => '09:00'] );
    } );

    it( 'rejects an invalid time limit', function (): void {
        $field = FormField::factory()->for( $this->form )->time()->create( [
            'validation_rules' => ['min_time' => '08:00'],
        ] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateTimeLimit', 'min_time', '25:00' );

        expect( $field->fresh()->validation_rules )->toBe( [] );
    } );

    it( 'ignores time limits on other field types', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'date', 'validation_rules' => []] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->call( 'updateTimeLimit', 'min_time', '09:00' )
            ->assertSet( 'isDirty', false );
    } );

    it( 'renders the controls for each field type', function ( string $type, array $expected ): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => $type] );

        $test = Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid );

        foreach ( $expected as $html ) {
            $test->assertSeeHtml( $html );
        }
    } )->with( [
        'textarea' => ['textarea', ["updateFieldConfig('rows', \$event.target.value)"]],
        'heading'  => ['heading', ["updateFieldConfig('level', \$event.target.value)", '<option value="h6"']],
        'divider'  => ['divider', ["updateFieldConfig('style', \$event.target.value)", "updateFieldConfig('spacing', \$event.target.value)"]],
        'time'     => ['time', [
            "updateTimeLimit('min_time', \$event.target.value)",
            "updateTimeLimit('max_time', \$event.target.value)",
            "updateFieldConfig('step', \$event.target.value)",
        ]],
        'checkbox' => ['checkbox', [
            "updateFieldConfig('checked_label', \$event.target.value)",
            "updateFieldConfig('unchecked_label', \$event.target.value)",
        ]],
    ] );

    it( 'does not render display controls for unrelated types', function (): void {
        $field = FormField::factory()->for( $this->form )->create( ['type' => 'text'] );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $field->uuid )
            ->assertDontSeeHtml( 'updateFieldConfig(' )
            ->assertDontSeeHtml( 'updateTimeLimit(' );
    } );
} );
