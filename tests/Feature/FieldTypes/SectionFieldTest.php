<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\Sections;
use Livewire\Livewire;

/**
 * Guards issue #125: the section layout element.
 */
beforeEach( function (): void {
    $this->form = Form::factory()->create( ['is_active' => true] );

    $this->field = fn ( string $name, string $type, int $order, array $attributes = [] ): FormField => FormField::factory()->for( $this->form )->create( array_merge( [
        'name'       => $name,
        'type'       => $type,
        'label'      => ucfirst( str_replace( '_', ' ', $name ) ),
        'help_text'  => null,
        'sort_order' => $order,
    ], $attributes ) );

    $this->choice = ( $this->field )( 'attending', 'radio', 0, ['field_config' => ['options' => [['value' => 'yes', 'label' => 'Yes'], ['value' => 'no', 'label' => 'No']]]] );
    $this->intro  = ( $this->field )( 'intro', 'text', 1 );
    $this->guests = ( $this->field )( 'guest_details', 'section', 2, [
        'label'             => 'Guest details',
        'field_config'      => ['description' => "Tell us who is coming.\nOne guest per line."],
        'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'attending', 'operator' => 'equals', 'value' => 'yes']]],
    ] );
    $this->guest  = ( $this->field )( 'guest_name', 'text', 3, ['is_required' => true] );
    $this->diet   = ( $this->field )( 'diet', 'textarea', 4 );
    $this->extras = ( $this->field )( 'extras', 'section', 5, ['label' => 'Extras'] );
    $this->notes  = ( $this->field )( 'notes', 'text', 6 );
} );

describe( 'definition', function (): void {
    it( 'is a layout element that supports conditional logic', function (): void {
        $definition = FieldTypes::getTypeConfig( 'section' );

        expect( $definition )->toMatchArray( ['is_layout' => true, 'supports_conditional_logic' => true, 'category' => 'layout'] )
            ->and( FieldTypes::getCategories()['layout']['fields'] )->toContain( 'section' )
            ->and( FieldSettings::find( $this->guests, 'description' ) )->toMatchArray( ['input' => 'textarea'] )
            ->and( FieldSettings::find( $this->guests, 'collapsible' ) )->toMatchArray( ['input' => 'toggle'] )
            ->and( FieldSettings::find( $this->guests, 'collapsed' ) )->toMatchArray( ['input' => 'toggle'] )
            ->and( $this->guests->buildValidationRules() )->toBe( [] );
    } );

    it( 'reads its description and collapse settings', function (): void {
        expect( $this->guests->getSectionDescription() )->toBe( "Tell us who is coming.\nOne guest per line." )
            ->and( $this->guests->isCollapsibleSection() )->toBeFalse()
            ->and( $this->guests->startsCollapsed() )->toBeFalse();

        $this->guests->update( ['field_config' => ['collapsed' => true]] );

        expect( $this->guests->fresh()->startsCollapsed() )->toBeFalse();

        $this->guests->update( ['field_config' => ['collapsible' => true, 'collapsed' => true]] );

        expect( $this->guests->fresh()->isCollapsibleSection() )->toBeTrue()
            ->and( $this->guests->fresh()->startsCollapsed() )->toBeTrue()
            ->and( $this->intro->getSectionDescription() )->toBeNull();
    } );
} );

describe( 'grouping', function (): void {
    it( 'groups the fields after each section up to the next one', function (): void {
        expect( Sections::members( $this->form->fields()->get()->shuffle() ) )->toBe( [
            'guest_details' => ['guest_name', 'diet'],
            'extras'        => ['notes'],
        ] );
    } );

    it( 'splits ordered fields into runs', function (): void {
        $segments = Sections::segments( $this->form->fields()->orderBy( 'sort_order' )->get() );

        expect( $segments )->toHaveCount( 3 )
            ->and( $segments[0]['section'] )->toBeNull()
            ->and( array_map( fn ( FormField $field ): string => $field->name, $segments[0]['fields'] ) )->toBe( ['attending', 'intro'] )
            ->and( $segments[1]['section']->name )->toBe( 'guest_details' )
            ->and( $segments[2]['section']->name )->toBe( 'extras' );
    } );

    it( 'ends a section at the end of its step', function (): void {
        $first  = FormStep::factory()->for( $this->form )->create( ['sort_order' => 0] );
        $second = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );

        $this->guests->update( ['step_id' => $first->id] );
        $this->guest->update( ['step_id' => $first->id] );
        $this->diet->update( ['step_id' => $second->id, 'sort_order' => 0] );

        expect( Sections::members( $this->form->fields()->get() )['guest_details'] )->toBe( ['guest_name'] );
    } );
} );

describe( 'conditional logic', function (): void {
    it( 'hides every field of a hidden section', function (): void {
        $service = app( ConditionalLogicService::class );
        $fields  = $this->form->fields()->get();

        expect( $service->getHiddenFields( $fields, ['attending' => 'no'] ) )->toMatchArray( [
            'guest_details' => true,
            'guest_name'    => true,
            'diet'          => true,
            'notes'         => false,
            'intro'         => false,
        ] )
            ->and( $service->getHiddenFields( $fields, ['attending' => 'yes'] ) )->toMatchArray( [
                'guest_details' => false,
                'guest_name'    => false,
            ] );
    } );

    it( 'keeps a field hidden by its own rule hidden in a shown section', function (): void {
        $this->diet->update( ['conditional_logic' => ['action' => 'hide', 'logic' => 'all', 'rules' => [['field' => 'intro', 'operator' => 'is_empty']]]] );

        expect( app( ConditionalLogicService::class )->getHiddenFields( $this->form->fields()->get(), ['attending' => 'yes', 'intro' => ''] ) )
            ->toMatchArray( ['guest_name' => false, 'diet' => true] );
    } );

    it( 'never hides a CAPTCHA in a hidden section', function (): void {
        ( $this->field )( 'challenge', 'captcha', 4 );

        expect( app( ConditionalLogicService::class )->getHiddenFields( $this->form->fields()->get(), ['attending' => 'no'] ) )
            ->toMatchArray( ['challenge' => false, 'guest_name' => true] );
    } );

    it( 'skips the validation of a hidden section\'s fields in Livewire and the API', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.attending', 'no' )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['attending' => 'yes'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( ['data.guest_name'] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['attending' => 'no', 'guest_name' => 'Ignored'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->latest( 'id' )->first()->values->pluck( 'field_name' )->all() )->not->toContain( 'guest_name' );
    } );

    it( 'does not store the values of a hidden section\'s fields from Livewire', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.attending', 'no' )
            ->set( 'formData.guest_name', str_repeat( 'x', 5000 ) )
            ->set( 'formData.notes', 'Kept' )
            ->call( 'submit' )
            ->assertSet( 'isSubmitted', true );

        $stored = $this->form->submissions()->sole()->values->pluck( 'value', 'field_name' );

        expect( $stored->keys()->all() )->not->toContain( 'guest_name' )
            ->and( $stored['notes'] )->toBe( 'Kept' );
    } );

    it( 'gives Alpine the section members without CAPTCHAs', function (): void {
        ( $this->field )( 'challenge', 'captcha', 4 );

        $component = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] );

        expect( $component->instance()->sectionMembers )->toBe( [
            'guest_details' => ['guest_name', 'diet'],
            'extras'        => ['notes'],
        ] );
        $component->assertSeeHtml( 'sections: ' );
    } );
} );

describe( 'rendering', function (): void {
    it( 'wraps a section\'s fields in a fieldset with a legend and description', function (): void {
        $html = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )->html();

        $fieldset = substr( $html, strpos( $html, 'id="section-guest_details"' ) );
        $fieldset = substr( $fieldset, 0, strpos( $fieldset, '</fieldset>' ) );

        expect( $html )->toContain( '<fieldset' )
            ->and( $fieldset )->toContain( 'aria-describedby="section-guest_details-description"' )
            ->toContain( 'Guest details' )
            ->toContain( 'Tell us who is coming.<br />' )
            ->toContain( 'id="field-guest_name"' )
            ->toContain( 'id="field-diet"' )
            ->not->toContain( 'id="field-notes"' )
            ->not->toContain( 'id="field-intro"' )
            ->and( strpos( $html, 'id="field-intro"' ) )->toBeLessThan( strpos( $html, 'id="section-guest_details"' ) );
    } );

    it( 'renders a collapsible section as a disclosure', function (): void {
        $this->guests->update( ['field_config' => ['collapsible' => true, 'collapsed' => true]] );

        $html = FieldRenderer::render( $this->guests->fresh() );

        expect( $html )->toContain( '<button' )
            ->toContain( 'aria-controls="section-guest_details-content"' )
            ->toContain( "x-bind:aria-expanded=\"open ? 'true' : 'false'\"" )
            ->toContain( 'id="section-guest_details-content"' )
            ->toContain( 'x-show="open"' )
            ->toContain( 'open: false' );
    } );

    it( 'opens a collapsed section with an error', function (): void {
        $this->guests->update( ['field_config' => ['collapsible' => true, 'collapsed' => true]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.attending', 'yes' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.guest_name'] )
            ->assertSeeHtml( 'open: true' )
            ->assertSeeHtml( 'x-init="true && (open = true)"' );
    } );

    it( 'keeps a fieldset holding a CAPTCHA shown and open', function (): void {
        $this->guests->update( ['field_config' => ['collapsible' => true, 'collapsed' => true]] );
        ( $this->field )( 'challenge', 'captcha', 4 );

        $html = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )->html();

        expect( $html )->toContain( 'x-show="open || !visible"' )
            ->and( FieldRenderer::render( $this->guests->fresh() ) )->toContain( 'x-show="visible" x-cloak' );
    } );

    it( 'keys each section so Livewire does not reuse one step\'s section for another', function (): void {
        expect( FieldRenderer::render( $this->guests ) )->toContain( 'wire:key="section-' . $this->guests->uuid . '"' );
    } );

    it( 'encodes the field name it reads from hiddenFields as a JavaScript string', function (): void {
        $this->notes->update( ['name' => "o'brien"] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->assertSeeHtml( "\$wire.hiddenFields?.['o\\u0027brien']" )
            ->assertDontSeeHtml( "hiddenFields?.['o'brien']" );
    } );
} );
