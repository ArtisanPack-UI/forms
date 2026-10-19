<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Security\SecurityServiceProvider;

describe( 'FormField Model', function (): void {
    describe( 'factory', function (): void {
        it( 'creates a valid form field', function (): void {
            $field = FormField::factory()->create();

            expect( $field )->toBeInstanceOf( FormField::class )
                ->and( $field->name )->not->toBeEmpty()
                ->and( $field->uuid )->not->toBeEmpty()
                ->and( $field->form )->toBeInstanceOf( Form::class );
        } );

        it( 'creates an email field', function (): void {
            $field = FormField::factory()->email()->create();

            expect( $field->type )->toBe( 'email' )
                ->and( $field->name )->toBe( 'email' );
        } );

        it( 'creates a required field', function (): void {
            $field = FormField::factory()->required()->create();

            expect( $field->is_required )->toBeTrue();
        } );

        it( 'creates a field with conditions', function (): void {
            $field = FormField::factory()->withConditions()->create();

            expect( $field->conditional_logic )->not->toBeEmpty()
                ->and( $field->has_conditional_logic )->toBeTrue();
        } );
    } );

    describe( 'boot', function (): void {
        it( 'auto-generates UUID', function (): void {
            $field = FormField::factory()->create( ['uuid' => null] );

            expect( $field->uuid )->not->toBeEmpty()
                ->and( strlen( $field->uuid ) )->toBe( 36 );
        } );
    } );

    describe( 'relationships', function (): void {
        it( 'belongs to a form', function (): void {
            $form  = Form::factory()->create();
            $field = FormField::factory()->for( $form )->create();

            expect( $field->form->id )->toBe( $form->id );
        } );

        it( 'belongs to a step', function (): void {
            $step  = FormStep::factory()->create();
            $field = FormField::factory()->for( $step->form )->state( ['step_id' => $step->id] )->create();

            expect( $field->step->id )->toBe( $step->id );
        } );

        it( 'has many submission values', function (): void {
            $field = FormField::factory()->create();
            FormSubmissionValue::factory()->count( 2 )->state( ['field_id' => $field->id] )->create();

            expect( $field->submissionValues )->toHaveCount( 2 );
        } );
    } );

    describe( 'scopes', function (): void {
        it( 'filters required fields', function (): void {
            FormField::factory()->create( ['is_required' => true] );
            FormField::factory()->create( ['is_required' => false] );

            expect( FormField::required()->count() )->toBe( 1 );
        } );

        it( 'filters by type', function (): void {
            FormField::factory()->create( ['type' => 'text'] );
            FormField::factory()->create( ['type' => 'email'] );

            expect( FormField::ofType( 'email' )->count() )->toBe( 1 );
        } );

        it( 'filters fields without step', function (): void {
            $step = FormStep::factory()->create();
            FormField::factory()->for( $step->form )->create( ['step_id' => null] );
            FormField::factory()->for( $step->form )->state( ['step_id' => $step->id] )->create();

            expect( FormField::withoutStep()->count() )->toBe( 1 );
        } );

        it( 'filters fields in step', function (): void {
            $step = FormStep::factory()->create();
            FormField::factory()->for( $step->form )->create( ['step_id' => null] );
            FormField::factory()->for( $step->form )->state( ['step_id' => $step->id] )->create();

            expect( FormField::inStep( $step->id )->count() )->toBe( 1 );
        } );
    } );

    describe( 'accessors', function (): void {
        it( 'detects conditional logic', function (): void {
            $fieldWith    = FormField::factory()->withConditions()->create();
            $fieldWithout = FormField::factory()->create( ['conditional_logic' => null] );

            expect( $fieldWith->has_conditional_logic )->toBeTrue()
                ->and( $fieldWithout->has_conditional_logic )->toBeFalse();
        } );

        it( 'detects file field', function (): void {
            $fileField = FormField::factory()->file()->create();
            $textField = FormField::factory()->text()->create();

            expect( $fileField->is_file_field )->toBeTrue()
                ->and( $textField->is_file_field )->toBeFalse();
        } );

        it( 'gets options from config', function (): void {
            $field = FormField::factory()->select()->create();

            expect( $field->options )->toBeArray()
                ->and( $field->options )->toHaveCount( 3 );
        } );

        it( 'returns width class', function (): void {
            expect( FormField::factory()->create( ['width' => 'full'] )->width_class )->toBe( 'w-full' )
                ->and( FormField::factory()->create( ['width' => 'half'] )->width_class )->toBe( 'w-full md:w-1/2' )
                ->and( FormField::factory()->create( ['width' => 'third'] )->width_class )->toBe( 'w-full md:w-1/3' )
                ->and( FormField::factory()->create( ['width' => 'two-thirds'] )->width_class )->toBe( 'w-full md:w-2/3' );
        } );
    } );

    describe( 'methods', function (): void {
        it( 'gets config value', function (): void {
            $field = FormField::factory()->create( [
                'field_config' => ['option' => 'value', 'nested' => ['key' => 'nested_value']],
            ] );

            expect( $field->getConfig( 'option' ) )->toBe( 'value' )
                ->and( $field->getConfig( 'nested.key' ) )->toBe( 'nested_value' )
                ->and( $field->getConfig( 'missing', 'default' ) )->toBe( 'default' );
        } );

        it( 'gets validation rule', function (): void {
            $field = FormField::factory()->create( [
                'validation_rules' => ['min' => 5, 'max' => 100],
            ] );

            expect( $field->getValidationRule( 'min' ) )->toBe( 5 )
                ->and( $field->getValidationRule( 'missing', 10 ) )->toBe( 10 );
        } );

        it( 'builds validation rules', function (): void {
            $field = FormField::factory()->required()->email()->create( [
                'validation_rules' => ['max' => 255],
            ] );

            $rules = $field->buildValidationRules();

            expect( $rules )->toContain( 'required' )
                ->and( $rules )->toContain( 'email' )
                ->and( $rules )->toContain( 'max:255' );
        } );

        it( 'builds validation rules for file field', function (): void {
            $field = FormField::factory()->required()->file()->create();

            $rules = $field->buildValidationRules();

            expect( $rules )->toContain( 'required' )
                ->and( $rules )->toContain( 'file' )
                ->and( $rules )->toContain( 'mimes:pdf,doc,docx' )
                ->and( $rules )->toContain( 'max:5120' );
        } );

        it( 'builds validation rules for time field with min and max time', function (): void {
            $field = FormField::factory()->required()->time()->create( [
                'validation_rules' => [
                    'min_time' => '09:00',
                    'max_time' => '17:00',
                ],
            ] );

            $rules = $field->buildValidationRules();

            expect( $rules )->toContain( 'required' )
                ->and( $rules )->toContain( 'date_format:H:i' )
                ->and( $rules )->toContain( 'after_or_equal:09:00' )
                ->and( $rules )->toContain( 'before_or_equal:17:00' );
        } );
    } );
} );

describe( 'FormField file settings (issue #79)', function (): void {
    it( 'reads max size and allowed types from field_config', function (): void {
        $field = FormField::factory()->file()->create( [
            'field_config' => ['max_size' => 100, 'allowed_types' => ['pdf']],
        ] );

        expect( $field->getFileMaxSize() )->toBe( 100 )
            ->and( $field->getFileAllowedTypes() )->toBe( ['pdf'] )
            ->and( $field->buildValidationRules() )->toContain( 'mimes:pdf', 'max:100' );
    } );

    it( 'prefers a legacy validation_rules value over the field_config default', function (): void {
        $field = FormField::factory()->file()->create( [
            'field_config'     => ['max_size' => 5120, 'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'png']],
            'validation_rules' => ['max_size' => 100, 'allowed_types' => ['pdf']],
        ] );

        expect( $field->buildValidationRules() )->toContain( 'mimes:pdf', 'max:100' )
            ->not->toContain( 'max:5120' );
    } );

    it( 'normalizes comma-separated, dotted and mixed-case extensions', function (): void {
        $field = FormField::factory()->file()->create( [
            'field_config' => ['allowed_types' => ' .PDF, jpg,, .Png ,pdf'],
        ] );

        expect( $field->getFileAllowedTypes() )->toBe( ['pdf', 'jpg', 'png'] );
    } );

    it( 'drops entries that are not plain extensions so they cannot alter the mimes rule', function (): void {
        $field = FormField::factory()->file()->create( [
            'field_config' => ['allowed_types' => ['pdf', 'png|required', 'x,y', 'image/png', '../exe', 'docx']],
        ] );

        expect( $field->getFileAllowedTypes() )->toBe( ['pdf', 'docx'] )
            ->and( $field->buildValidationRules() )->toContain( 'mimes:pdf,docx' );
    } );

    it( 'treats a missing, zero or non-numeric max size as unlimited', function ( mixed $maxSize ): void {
        $field = FormField::factory()->file()->create( ['field_config' => ['max_size' => $maxSize]] );

        expect( $field->getFileMaxSize() )->toBeNull()
            ->and( implode( '|', $field->buildValidationRules() ) )->not->toContain( 'max:' );
    } )->with( [
        'null'        => [null],
        'zero'        => [0],
        'negative'    => [-5],
        'non-numeric' => ['big'],
    ] );

    it( 'adds no mimes rule when no types are allowed', function (): void {
        $field = FormField::factory()->file()->create( ['field_config' => ['allowed_types' => []]] );

        expect( $field->getFileAllowedTypes() )->toBe( [] )
            ->and( implode( '|', $field->buildValidationRules() ) )->not->toContain( 'mimes:' );
    } );
} );

describe( 'FormField required acceptance (issue #81)', function (): void {
    it( 'uses accepted instead of required for a required checkbox or toggle', function ( string $type ): void {
        $field = FormField::factory()->required()->create( ['type' => $type] );

        expect( $field->buildValidationRules() )->toBe( ['accepted', 'boolean'] );
    } )->with( ['checkbox', 'toggle'] );

    it( 'keeps an optional checkbox nullable', function (): void {
        $field = FormField::factory()->create( ['type' => 'checkbox', 'is_required' => false] );

        expect( $field->buildValidationRules() )->toBe( ['nullable', 'boolean'] );
    } );

    it( 'still uses required for other field types', function (): void {
        $field = FormField::factory()->required()->create( ['type' => 'checkbox_group'] );

        expect( $field->buildValidationRules() )->toContain( 'required' )
            ->not->toContain( 'accepted' );
    } );
} );

describe( 'FormField layout content (issue #82)', function (): void {
    it( 'reads each layout type from its canonical column', function ( array $attributes, string $expected ): void {
        $field = FormField::factory()->create( $attributes );

        expect( $field->getLayoutContent() )->toBe( $expected );
    } )->with( [
        'heading → label'                     => [['type' => 'heading', 'label' => 'Contact us'], 'Contact us'],
        'paragraph → field_config.content'    => [['type' => 'paragraph', 'label' => 'Info', 'field_config' => ['content' => 'Body text']], 'Body text'],
        'html → field_config.content'         => [['type' => 'html', 'label' => 'Custom', 'field_config' => ['content' => '<b>Hi</b>']], '<b>Hi</b>'],
    ] );

    it( 'prefers legacy React/Vue default_value content', function ( string $type ): void {
        $field = FormField::factory()->create( [
            'type'          => $type,
            'label'         => 'Section Heading',
            'default_value' => 'Legacy content',
            'field_config'  => ['content' => 'Stale'],
        ] );

        expect( $field->getLayoutContent() )->toBe( 'Legacy content' );
    } )->with( ['heading', 'paragraph', 'html'] );

    it( 'falls back to help_text, then label, for a legacy Livewire paragraph', function (): void {
        $withHelp = FormField::factory()->create( ['type' => 'paragraph', 'label' => 'Info', 'help_text' => 'Help body'] );
        $bare     = FormField::factory()->create( ['type' => 'paragraph', 'label' => 'Info', 'help_text' => null] );

        expect( $withHelp->getLayoutContent() )->toBe( 'Help body' )
            ->and( $bare->getLayoutContent() )->toBe( 'Info' );
    } );

    it( 'ignores blank legacy and content values', function (): void {
        $field = FormField::factory()->create( [
            'type'          => 'html',
            'default_value' => '   ',
            'field_config'  => ['content' => ''],
        ] );

        expect( $field->getLayoutContent() )->toBe( '' );
    } );

    it( 'returns an empty string for non-content types', function (): void {
        expect( FormField::factory()->create( ['type' => 'text', 'label' => 'Name'] )->getLayoutContent() )->toBe( '' );
    } );
} );

describe( 'FormField html sanitizing', function (): void {
    beforeEach( function (): void {
        $this->app->register( SecurityServiceProvider::class );
    } );

    it( 'strips scripts, event handlers, javascript: URLs, embeds and styles', function (): void {
        $html = FormField::sanitizeHtml(
            '<p class="lead" style="color:red">Hi <a href="https://example.com">ok</a></p>'
            . '<script>alert(1)</script><img src="/a.png" onerror="alert(1)">'
            . '<a href="javascript:alert(1)">x</a><iframe src="https://evil.test"></iframe>'
            . '<svg onload="alert(1)"></svg><style>body{display:none}</style>',
        );

        expect( $html )->toContain( '<p class="lead">Hi <a href="https://example.com">ok</a></p>' )
            ->not->toContain( '<script' )
            ->not->toContain( 'onerror' )
            ->not->toContain( 'onload' )
            ->not->toContain( 'href="javascript:' )
            ->not->toContain( '<iframe' )
            ->not->toContain( '<style' )
            ->not->toContain( 'style=' );
    } );
} );
