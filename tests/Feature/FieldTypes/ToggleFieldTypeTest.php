<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\ToggleFieldType;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #93: the toggle (switch) field type.
 */

/**
 * Validates one toggle value against a toggle field's rules.
 */
function togglePasses( FormField $field, mixed $value ): bool
{
    return Validator::make( ['toggle' => $value], ['toggle' => $field->buildValidationRules()] )->passes();
}

describe( 'toggle field type registration', function (): void {
    it( 'is registered in the choice category with size, color and on/off text settings', function (): void {
        $config = FieldTypes::getTypeConfig( 'toggle' );

        expect( FieldTypes::typeExists( 'toggle' ) )->toBeTrue()
            ->and( FieldTypes::CATEGORY_FIELDS['choice'] )->toContain( 'toggle' )
            ->and( FieldTypes::handler( 'toggle' ) )->toBeInstanceOf( ToggleFieldType::class )
            ->and( $config )->toMatchArray( [
                'label'                  => 'Toggle',
                'category'               => 'choice',
                'supports_default_value' => true,
            ] )
            ->and( array_column( $config['settings'], 'key' ) )->toContain( 'size', 'color', 'checked_label', 'unchecked_label', 'read_only' );

        $settings = collect( $config['settings'] )->keyBy( 'key' );

        expect( array_column( $settings['size']['options'], 'value' ) )->toBe( ToggleFieldType::SIZES )
            ->and( array_column( $settings['color']['options'], 'value' ) )->toBe( ToggleFieldType::COLORS );
    } );

    it( 'can be added from the Livewire builder palette', function (): void {
        $form = Form::factory()->create();

        Livewire::actingAs( User::factory()->create() )->test( FormBuilder::class, ['form' => $form] )
            ->assertSee( 'Toggle' )
            ->call( 'addField', 'toggle' );

        expect( $form->fields()->first()->type )->toBe( 'toggle' );
    } );

    it( 'supports the checked and unchecked operators in conditional logic', function (): void {
        expect( array_keys( ConditionalLogic::getOperatorsForType( 'toggle' ) ) )
            ->toContain( 'checked', 'unchecked', 'is_empty', 'is_not_empty' );
    } );
} );

describe( 'toggle validation', function (): void {
    it( 'is a boolean, optional by default', function (): void {
        $field = FormField::factory()->create( ['type' => 'toggle'] );

        expect( $field->buildValidationRules() )->toBe( ['nullable', 'boolean'] )
            ->and( togglePasses( $field, false ) )->toBeTrue()
            ->and( togglePasses( $field, true ) )->toBeTrue()
            ->and( togglePasses( $field, 'maybe' ) )->toBeFalse();
    } );

    it( 'must be on when required', function (): void {
        $field = FormField::factory()->required()->create( ['type' => 'toggle'] );

        expect( $field->buildValidationRules() )->toBe( ['accepted', 'boolean'] )
            ->and( togglePasses( $field, false ) )->toBeFalse()
            ->and( togglePasses( $field, true ) )->toBeTrue();
    } );
} );

describe( 'toggle storage, display and export', function (): void {
    beforeEach( function (): void {
        $this->form  = Form::factory()->create();
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'  => 'toggle',
            'name'  => 'subscribe',
            'label' => 'Subscribe',
        ] );
    } );

    it( 'shows Yes / No by default', function (): void {
        $on  = FormSubmissionValue::factory()->make( ['field_id' => $this->field->id, 'field_type' => 'toggle', 'value' => '1', 'value_array' => null] );
        $off = FormSubmissionValue::factory()->make( ['field_id' => $this->field->id, 'field_type' => 'toggle', 'value' => '0', 'value_array' => null] );

        expect( $on->display_value )->toBe( 'Yes' )
            ->and( $off->display_value )->toBe( 'No' );
    } );

    it( 'shows and exports the configured on/off text', function (): void {
        $this->field->update( ['field_config' => ['checked_label' => 'Subscribed', 'unchecked_label' => 'Not subscribed']] );

        $submission = app( SubmissionService::class )->create( $this->form, ['subscribe' => true] );
        $submission->load( 'values.field' );

        $row = app( ExportService::class )->buildRow( $this->form, $submission, $this->form->fields()->get() );

        expect( $submission->getValue( 'subscribe' ) )->toBe( 'Subscribed' )
            ->and( $row[3] )->toBe( 'Subscribed' );
    } );

    it( 'keeps the default value when read-only', function (): void {
        $this->field->update( ['default_value' => 'yes', 'field_config' => ['read_only' => true]] );

        $submission = app( SubmissionService::class )->create( $this->form, ['subscribe' => false] );

        expect( $submission->values()->first()->value )->toBe( '1' );
    } );
} );

describe( 'toggle Livewire renderer', function (): void {
    it( 'renders a switch with its size and color', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->required()->create( [
            'type'         => 'toggle',
            'name'         => 'subscribe',
            'label'        => 'Subscribe',
            'field_config' => ['size' => 'lg', 'color' => 'success'],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSeeHtml( 'role="switch"' )
            ->assertSeeHtml( 'toggle-lg' )
            ->assertSeeHtml( 'toggle-success' )
            ->assertSeeHtml( 'aria-required="true"' )
            ->assertDontSeeHtml( 'aria-readonly' );
    } );

    it( 'disables a read-only switch', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'         => 'toggle',
            'name'         => 'subscribe',
            'field_config' => ['read_only' => true],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSeeHtml( 'aria-readonly="true"' );
    } );

    it( 'requires a required toggle to be switched on', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->required()->create( ['type' => 'toggle', 'name' => 'terms'] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->afterSpamDelay()
            ->set( 'formData.terms', false )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.terms' => 'accepted'] )
            ->set( 'formData.terms', true )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        expect( FormSubmission::query()->first()->getValue( 'terms' ) )->toBe( 'Yes' );
    } );
} );
