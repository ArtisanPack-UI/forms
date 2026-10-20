<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Http\Request;
use Livewire\Livewire;

describe( 'read-only fields', function (): void {
    it( 'is read-only only when the setting is on and the type offers it', function ( string $type, array $config, bool $expected ): void {
        $field = FormField::factory()->make( ['type' => $type, 'field_config' => $config] );

        expect( $field->isReadOnly() )->toBe( $expected );
    } )->with( [
        'text, on'           => ['text', ['read_only' => true], true],
        'text, string true'  => ['text', ['read_only' => '1'], true],
        'text, off'          => ['text', [], false],
        'select, on'         => ['select', ['read_only' => true], true],
        'rating, on'         => ['rating', ['read_only' => true], true],
        'file, on'           => ['file', ['read_only' => true], false],
        'hidden, on'         => ['hidden', ['read_only' => true], false],
        'checkbox group, on' => ['checkbox_group', ['read_only' => true], false],
        'heading, on'        => ['heading', ['read_only' => true], false],
    ] );

    it( 'offers the read-only setting in the Advanced tab of types with a default value', function (): void {
        $field = FormField::factory()->make( ['type' => 'email'] );

        expect( collect( $field->fieldType()->definition()['settings'] )->firstWhere( 'key', 'read_only' ) )
            ->toMatchArray( ['input' => 'toggle', 'tab' => 'advanced', 'store' => 'field_config'] );
    } );

    it( 'stores the default value whatever the client sent', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'          => 'text',
            'name'          => 'plan',
            'default_value' => 'Pro',
            'field_config'  => ['read_only' => true],
        ] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'name'] );

        $submission = app( SubmissionService::class )->create( $form, ['plan' => 'Enterprise', 'name' => 'Ada'] );
        $values     = $submission->values()->get()->keyBy( 'field_name' );

        expect( $values['plan']->value )->toBe( 'Pro' )
            ->and( $values['name']->value )->toBe( 'Ada' );
    } );

    it( 'stores the value through the API even when the client left the field out', function (): void {
        $form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $form )->create( [
            'type'          => 'number',
            'name'          => 'seats',
            'default_value' => '3',
            'field_config'  => ['read_only' => true],
        ] );

        $this->postJson( route( 'api.forms.submit', $form ), [
            'data'            => [],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $form->submissions()->first()->getValue( 'seats' ) )->toBe( '3' );
    } );

    it( 'leaves out a read-only field the caller did not pass to the service', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'          => 'number',
            'name'          => 'seats',
            'default_value' => '3',
            'field_config'  => ['read_only' => true],
        ] );

        $submission = app( SubmissionService::class )->create( $form, [] );

        expect( $submission->getValue( 'seats' ) )->toBeNull();
    } );

    it( 'does not store a read-only field that conditional logic hides', function (): void {
        $form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'plan'] );
        FormField::factory()->for( $form )->create( [
            'type'              => 'text',
            'name'              => 'discount',
            'default_value'     => 'TEAM10',
            'field_config'      => ['read_only' => true],
            'conditional_logic' => [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [['field' => 'plan', 'operator' => 'equals', 'value' => 'team']],
            ],
        ] );

        $this->postJson( route( 'api.forms.submit', $form ), [
            'data'            => ['plan' => 'solo', 'discount' => 'TEAM10'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        $submission = $form->submissions()->first();

        expect( $submission->getValue( 'plan' ) )->toBe( 'solo' )
            ->and( $submission->getValue( 'discount' ) )->toBeNull();
    } );

    it( 'decides field visibility from the read-only value, not a tampered one', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'          => 'text',
            'name'          => 'plan',
            'default_value' => 'business',
            'field_config'  => ['read_only' => true],
        ] );
        FormField::factory()->for( $form )->create( [
            'type'              => 'text',
            'name'              => 'company_vat',
            'is_required'       => true,
            'conditional_logic' => [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [['field' => 'plan', 'operator' => 'equals', 'value' => 'business']],
            ],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.plan', 'personal' )
            ->assertSet( 'hiddenFields.company_vat', true )
            ->call( 'submit' )
            ->assertHasErrors( 'formData.company_vat' )
            ->assertSet( 'isSubmitted', false );
    } );

    it( 'lets a package supply the value through the readOnlyValue filter', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'         => 'text',
            'name'         => 'ref',
            'field_config' => ['read_only' => true],
        ] );

        addFilter( 'ap.forms.readOnlyValue', fn ( mixed $value, FormField $field ): mixed => 'ref' === $field->name ? 'ABC-123' : $value );

        $submission = app( SubmissionService::class )->create( $form, ['ref' => 'tampered'] );

        expect( $submission->getValue( 'ref' ) )->toBe( 'ABC-123' );

        removeAllFilters( 'ap.forms.readOnlyValue' );
    } );

    it( 'ignores a tampered value in the Livewire renderer and validates the real one', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'          => 'email',
            'name'          => 'owner',
            'default_value' => 'owner@example.com',
            'field_config'  => ['read_only' => true],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSet( 'formData.owner', 'owner@example.com' )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.owner', 'not-an-email' )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        expect( $form->submissions()->first()->getValue( 'owner' ) )->toBe( 'owner@example.com' );
    } );

    it( 'ignores a tampered value in the API', function (): void {
        $form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $form )->create( [
            'type'          => 'select',
            'name'          => 'tier',
            'default_value' => 'basic',
            'field_config'  => ['read_only' => true, 'options' => [['value' => 'basic', 'label' => 'Basic'], ['value' => 'gold', 'label' => 'Gold']]],
        ] );

        $this->postJson( route( 'api.forms.submit', $form ), [
            'data'            => ['tier' => 'gold'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $form->submissions()->first()->getValue( 'tier' ) )->toBe( 'Basic' );
    } );

    it( 'submits a read-only checkbox whose default is a word such as "yes"', function ( string $default, bool $checked ): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'          => 'checkbox',
            'name'          => 'subscribed',
            'default_value' => $default,
            'field_config'  => ['read_only' => true],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSet( 'formData.subscribed', $checked )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );
    } )->with( [
        'yes'   => ['yes', true],
        'true'  => ['true', true],
        'on'    => ['on', true],
        'blank' => ['', false],
    ] );

    it( 'exposes the value a read-only field submits in the field resource', function (): void {
        addFilter( 'ap.forms.readOnlyValue', fn (): string => 'ABC-123' );

        $readOnly = FormField::factory()->make( ['type' => 'text', 'field_config' => ['read_only' => true]] );
        $editable = FormField::factory()->make( ['type' => 'text'] );

        $request = Request::create( '/' );

        expect( ( new FormFieldResource( $readOnly ) )->resolve( $request )['read_only_value'] )->toBe( 'ABC-123' )
            ->and( ( new FormFieldResource( $editable ) )->resolve( $request ) )->not->toHaveKey( 'read_only_value' );

        removeAllFilters( 'ap.forms.readOnlyValue' );
    } );

    it( 'renders text inputs readonly and choices aria-readonly and disabled', function (): void {
        $form = Form::factory()->create();

        foreach ( ['text', 'textarea', 'date', 'time', 'radio', 'checkbox', 'rating'] as $index => $type ) {
            FormField::factory()->for( $form )->create( [
                'type'         => $type,
                'name'         => "field_{$type}",
                'sort_order'   => $index,
                'field_config' => ['read_only' => true, 'options' => [['value' => 'a', 'label' => 'A']]],
            ] );
        }

        $html = Livewire::test( FormRenderer::class, ['form' => $form] )->html();

        $tag = static function ( string $html, string $id ): string {
            preg_match( '/<(?:input|textarea|select)\b[^>]*id="' . preg_quote( $id, '/' ) . '"[^>]*>/s', $html, $matches );

            return $matches[0] ?? '';
        };

        foreach ( ['field-field_text', 'field-field_textarea', 'field-field_date', 'field-field_time'] as $id ) {
            expect( $tag( $html, $id ) )->toContain( 'readonly' )->not->toContain( 'disabled' );
        }

        expect( $tag( $html, 'field-field_radio-0' ) )->toContain( 'disabled' )
            ->and( $tag( $html, 'field-field_checkbox' ) )->toContain( 'disabled' )
            ->and( $tag( $html, 'field-field_checkbox' ) )->toContain( 'aria-readonly="true"' )
            ->and( $tag( $html, 'field-field_rating-1' ) )->toContain( 'disabled' )
            ->and( substr_count( $html, 'aria-readonly="true"' ) )->toBeGreaterThanOrEqual( 3 );
    } );

    it( 'renders editable fields without read-only attributes', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'name'] );
        FormField::factory()->for( $form )->create( ['type' => 'radio', 'name' => 'pick', 'field_config' => ['options' => [['value' => 'a', 'label' => 'A']]]] );

        $html = Livewire::test( FormRenderer::class, ['form' => $form] )->html();

        preg_match( '/<input\b[^>]*id="field-pick-0"[^>]*>/s', $html, $radio );

        expect( $html )->not->toContain( 'readonly' )
            ->and( $html )->not->toContain( 'aria-readonly' )
            ->and( $radio[0] ?? '' )->not->toBe( '' )->not->toContain( 'disabled' );
    } );
} );
