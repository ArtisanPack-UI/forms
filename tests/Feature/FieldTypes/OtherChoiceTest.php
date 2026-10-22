<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #103: an "Other" choice with a free-text input on radio and
 * checkbox group fields.
 */
describe( '"Other" choice settings', function (): void {
    it( 'are General tab settings on radio and checkbox group fields', function ( string $type ): void {
        $field = FormField::factory()->make( ['type' => $type] );

        expect( FieldSettings::find( $field, 'enable_other' ) )->toMatchArray( ['tab' => 'general', 'input' => 'toggle'] )
            ->and( FieldSettings::find( $field, 'other_label' ) )->toMatchArray( ['tab' => 'general', 'input' => 'text', 'max_length' => 255] );
    } )->with( ['radio', 'checkbox_group'] );

    it( 'are not offered on other choice types', function ( string $type ): void {
        $field = FormField::factory()->make( ['type' => $type, 'field_config' => ['enable_other' => true]] );

        expect( FieldSettings::find( $field, 'enable_other' ) )->toBeNull()
            ->and( $field->allowsOtherChoice() )->toBeFalse();
    } )->with( ['select', 'select_multiple', 'checkbox'] );

    it( 'label the choice "Other" unless set', function (): void {
        expect( FormField::factory()->make( ['type' => 'radio', 'field_config' => ['enable_other' => true]] )->getOtherChoiceLabel() )->toBe( 'Other' )
            ->and( FormField::factory()->make( ['type' => 'radio', 'field_config' => ['enable_other' => true, 'other_label' => 'Something else']] )->getOtherChoiceLabel() )->toBe( 'Something else' );
    } );

    it( 'add the choice to the options conditional logic offers', function (): void {
        $field = FormField::factory()->make( [
            'type'         => 'radio',
            'field_config' => ['enable_other' => true, 'options' => [['value' => 'a', 'label' => 'A']]],
        ] );

        expect( $field->getConditionOptions() )->toBe( [['value' => 'a', 'label' => 'A'], ['value' => '__other__', 'label' => 'Other']] );
    } );

    it( 'expose the choice label in the render payload only when on', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'radio', 'name' => 'with', 'field_config' => ['enable_other' => true, 'other_label' => 'Else']] );
        FormField::factory()->for( $form )->create( ['type' => 'radio', 'name' => 'without', 'sort_order' => 2] );

        $fields = collect( ( new FormRenderResource( $form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve() )->keyBy( 'name' );

        expect( $fields['with']['other_choice_label'] )->toBe( 'Else' )
            ->and( $fields['without'] )->not->toHaveKey( 'other_choice_label' );
    } );
} );

describe( '"Other" choice on a radio field', function (): void {
    beforeEach( function (): void {
        $this->form  = Form::factory()->create( ['is_active' => true] );
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'radio',
            'name'         => 'source',
            'label'        => 'How did you hear about us?',
            'field_config' => ['enable_other' => true, 'options' => [['value' => 'search', 'label' => 'Search engine'], ['value' => 'friend', 'label' => 'A friend']]],
        ] );
    } );

    it( 'renders the choice last and reveals a labelled text input once selected', function (): void {
        $closed = FieldRenderer::render( $this->field, 'search' );
        $open   = FieldRenderer::render( $this->field, '__other__' );

        expect( $closed )->toContain( 'id="field-source-other"' )
            ->toContain( 'value="__other__"' )
            ->not->toContain( 'field-source-other-text' )
            ->and( $open )->toContain( 'id="field-source-other-text"' )
            ->toContain( 'wire:model="otherValues.source"' )
            ->toContain( 'Other, please specify' )
            ->and( strpos( $open, 'id="field-source-other-text"' ) )->toBeGreaterThan( strpos( $open, 'id="field-source-other"' ) );
    } );

    it( 'stores the typed text and shows it as "Other: …"', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.source', '__other__' )
            ->assertSee( 'Other, please specify' )
            ->set( 'otherValues.source', '  A podcast  ' )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value )->toBe( 'A podcast' )
            ->and( $value->display_value )->toBe( 'Other: A podcast' );
    } );

    it( 'requires the text when the choice is selected', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.source', '__other__' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.source' => SubmissionRules::OTHER_CHOICE] )
            ->assertSet( 'formData.source', '__other__' )
            ->assertSet( 'isSubmitted', false );
    } );

    it( 'still stores a listed option', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.source', 'friend' )
            ->set( 'otherValues.source', 'ignored' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->getValue( 'source' ) )->toBe( 'A friend' );
    } );

    it( 'accepts the typed text through the API and rejects the bare placeholder', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['source' => 'A billboard'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['source' => '__other__'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.source' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['source' => str_repeat( 'x', FormField::OTHER_CHOICE_MAX_LENGTH + 1 )],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.source' );

        expect( $this->form->submissions()->first()->getValue( 'source' ) )->toBe( 'Other: A billboard' );
    } );

    it( 'shows an unmatched value as-is when the choice is off', function (): void {
        $this->field->update( ['field_config' => ['options' => [['value' => 'search', 'label' => 'Search engine']]]] );

        $submission = app( SubmissionService::class )->create( $this->form, ['source' => 'A billboard'] );

        expect( $submission->getValue( 'source' ) )->toBe( 'A billboard' );
    } );
} );

describe( '"Other" choice on a checkbox group', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'checkbox_group',
            'name'         => 'topics',
            'label'        => 'Topics',
            'field_config' => ['enable_other' => true, 'other_label' => 'Something else', 'options' => [['value' => 'news', 'label' => 'News']]],
        ] );
    } );

    it( 'stores the text in the value array and labels it', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.topics', ['news', '__other__'] )
            ->set( 'otherValues.topics', 'Gardening' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value_array )->toBe( ['news', 'Gardening'] )
            ->and( $value->display_values )->toBe( ['News', 'Something else: Gardening'] );
    } );

    it( 'requires the text when the choice is checked', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.topics', ['__other__'] )
            ->call( 'submit' )
            ->assertHasErrors( 'formData.topics' );
    } );

    it( 'resolves the placeholder without duplicating a listed option', function (): void {
        $fields = $this->form->fields;

        expect( app( SubmissionService::class )->resolveOtherChoices( $fields, ['topics' => ['news', '__other__']], ['topics' => 'news'] ) )
            ->toBe( ['topics' => ['news']] );
    } );
} );

describe( '"Other" choice in conditional logic', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'radio',
            'name'         => 'source',
            'field_config' => ['enable_other' => true, 'options' => [['value' => 'search', 'label' => 'Search']]],
        ] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'checkbox_group',
            'name'         => 'topics',
            'sort_order'   => 2,
            'field_config' => ['enable_other' => true, 'options' => [['value' => 'news', 'label' => 'News']]],
        ] );
        $this->details = FormField::factory()->for( $this->form )->create( [
            'type'              => 'text',
            'name'              => 'details',
            'sort_order'        => 3,
            'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'source', 'operator' => 'equals', 'value' => '__other__']]],
        ] );
    } );

    it( 'matches the placeholder while the form is filled in and the typed text once submitted', function ( mixed $source, bool $hidden ): void {
        $hiddenFields = app( ConditionalLogicService::class )->getHiddenFields( $this->form->fields, ['source' => $source] );

        expect( $hiddenFields['details'] )->toBe( $hidden );
    } )->with( [
        'placeholder' => ['__other__', false],
        'typed text'  => ['A podcast', false],
        'an option'   => ['search', true],
        'blank'       => ['', true],
    ] );

    it( 'matches an "Other" item in a checkbox group', function (): void {
        $this->details->update( ['conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'topics', 'operator' => 'includes', 'value' => '__other__']]]] );
        $service = app( ConditionalLogicService::class );
        $fields  = $this->form->fields()->get();

        expect( $service->getHiddenFields( $fields, ['topics' => ['news', 'Gardening']] )['details'] )->toBeFalse()
            ->and( $service->getHiddenFields( $fields, ['topics' => ['news']] )['details'] )->toBeTrue();
    } );

    it( 'sends a notification whose condition tests for "Other"', function (): void {
        $notification = FormNotification::factory()->for( $this->form )->create( [
            'conditional_logic' => ['action' => 'send', 'logic' => 'all', 'rules' => [['field' => 'source', 'operator' => 'equals', 'value' => '__other__']]],
        ] );
        $service = app( NotificationService::class );

        $other  = app( SubmissionService::class )->create( $this->form, ['source' => 'A podcast'] );
        $search = app( SubmissionService::class )->create( $this->form, ['source' => 'search'] );

        expect( $service->shouldSendNotification( $notification, $other ) )->toBeTrue()
            ->and( $service->shouldSendNotification( $notification, $search ) )->toBeFalse();
    } );

    it( 'offers "Other" in the Livewire condition builder', function (): void {
        $user = Tests\Models\User::factory()->create();

        $this->actingAs( $user );

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->details->uuid )
            ->assertSeeHtml( '__other__' );
    } );
} );
