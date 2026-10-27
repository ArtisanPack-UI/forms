<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #154: the Livewire form builder checks the `update` ability on
 * mount and in every action that changes the form, and updateField() only
 * changes the attributes the builder edits.
 */
beforeEach( function (): void {
    config( ['artisanpack.forms.authorization.restrict_by_owner' => true] );

    $this->owner    = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->form     = Form::factory()->create( ['user_id' => $this->owner->id, 'is_multi_step' => true] );
    $this->step     = FormStep::factory()->for( $this->form )->create();
    $this->field    = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'step_id' => $this->step->id] );
} );

describe( 'mounting the builder', function (): void {
    it( 'is allowed for a user who may update the form', function (): void {
        Livewire::actingAs( $this->owner )->test( FormBuilder::class, ['form' => $this->form] )
            ->assertSuccessful();
    } );

    it( 'is forbidden for a user who may not update the form', function (): void {
        Livewire::actingAs( $this->stranger )->test( FormBuilder::class, ['form' => $this->form] )
            ->assertForbidden();
    } );

    it( 'is forbidden for a guest', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->assertForbidden();
    } );
} );

describe( 'builder actions', function (): void {
    it( 'are forbidden once the user may no longer update the form', function ( string $method, Closure $arguments ): void {
        $builder = Livewire::actingAs( $this->owner )->test( FormBuilder::class, ['form' => $this->form] )
            ->set( 'selectedFieldId', $this->field->uuid )
            ->set( 'isDirty', true );

        $this->actingAs( $this->stranger );

        $builder->call( $method, ...$arguments->call( $this ) )->assertForbidden();

        expect( $this->field->fresh() )->not->toBeNull()
            ->and( $this->field->fresh()->label )->toBe( 'Name' )
            ->and( $this->form->fields()->count() )->toBe( 1 )
            ->and( $this->form->steps()->count() )->toBe( 1 );
    } )->with( [
        'addField'               => ['addField', fn (): array => ['text']],
        'updateField'            => ['updateField', fn (): array => [['label' => 'Changed']]],
        'updateSetting'          => ['updateSetting', fn (): array => ['input_mask', 'phone']],
        'updateLayoutContent'    => ['updateLayoutContent', fn (): array => ['Changed']],
        'storeChoiceImage'       => ['storeChoiceImage', fn (): array => []],
        'applyBulkChoices'       => ['applyBulkChoices', fn (): array => ["a\nb"]],
        'updateOptions'          => ['updateOptions', fn (): array => [[['value' => 'a', 'label' => 'A']]]],
        'updateSubInput'         => ['updateSubInput', fn (): array => ['first', 'label', 'Given']],
        'updateConditionalLogic' => ['updateConditionalLogic', fn (): array => [['action' => 'show', 'logic' => 'all', 'rules' => []]]],
        'clearConditionalLogic'  => ['clearConditionalLogic', fn (): array => []],
        'deleteField'            => ['deleteField', fn (): array => [$this->field->uuid]],
        'duplicateField'         => ['duplicateField', fn (): array => [$this->field->uuid]],
        'reorderFields'          => ['reorderFields', fn (): array => [[$this->field->uuid]]],
        'addStep'                => ['addStep', fn (): array => []],
        'updateStep'             => ['updateStep', fn (): array => [$this->step->id, ['title' => 'Changed']]],
        'deleteStep'             => ['deleteStep', fn (): array => [$this->step->id]],
        'reorderSteps'           => ['reorderSteps', fn (): array => [[$this->step->id]]],
        'enableMultiStep'        => ['enableMultiStep', fn (): array => []],
        'disableMultiStep'       => ['disableMultiStep', fn (): array => []],
        'saveForm'               => ['saveForm', fn (): array => []],
    ] );

    it( 'still work for an admin when ownership is restricted', function (): void {
        $admin = User::factory()->create( ['is_admin' => true] );

        Livewire::actingAs( $admin )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->call( 'updateField', ['label' => 'Full Name'] )
            ->assertSuccessful();

        expect( $this->field->fresh()->label )->toBe( 'Full Name' );
    } );
} );

describe( 'active step', function (): void {
    it( 'cannot be pointed at another form\'s step', function (): void {
        $foreign = FormStep::factory()->for( Form::factory()->create( ['is_multi_step' => true] ) )->create();

        $builder = Livewire::actingAs( $this->owner )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectStep', $foreign->id )
            ->assertSet( 'activeStepId', $this->step->id )
            ->call( 'addField', 'text' );

        expect( FormField::where( 'step_id', $foreign->id )->exists() )->toBeFalse()
            ->and( $this->form->fields()->latest( 'id' )->first()->step_id )->toBe( $this->step->id );

        expect( fn () => $builder->set( 'activeStepId', $foreign->id ) )->toThrow( CannotUpdateLockedPropertyException::class );
    } );
} );

describe( 'updateStep', function (): void {
    beforeEach( function (): void {
        $this->builder = Livewire::actingAs( $this->owner )->test( FormBuilder::class, ['form' => $this->form] );
    } );

    it( 'changes the attributes the step editor edits', function (): void {
        $this->builder->call( 'updateStep', $this->step->id, [
            'title'            => 'Contact',
            'description'      => 'How to reach you',
            'prev_button_text' => 'Back',
            'next_button_text' => 'Onward',
        ] )->assertHasNoErrors();

        expect( $this->step->fresh() )
            ->title->toBe( 'Contact' )
            ->description->toBe( 'How to reach you' )
            ->prev_button_text->toBe( 'Back' )
            ->next_button_text->toBe( 'Onward' );
    } );

    it( 'ignores the step\'s form and order', function (): void {
        $otherForm = Form::factory()->create();

        $this->builder->call( 'updateStep', $this->step->id, ['title' => 'Contact', 'form_id' => $otherForm->id, 'sort_order' => 99] );

        expect( $this->step->fresh() )
            ->title->toBe( 'Contact' )
            ->form_id->toBe( $this->form->id )
            ->sort_order->toBe( $this->step->sort_order );
    } );

    it( 'rejects invalid values', function (): void {
        $this->builder->call( 'updateStep', $this->step->id, ['title' => str_repeat( 'x', 256 )] )
            ->assertHasErrors( 'title' );

        expect( $this->step->fresh()->title )->toBe( $this->step->title );
    } );
} );

describe( 'updateField', function (): void {
    beforeEach( function (): void {
        $this->builder = Livewire::actingAs( $this->owner )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid );
    } );

    it( 'changes the attributes the builder edits', function (): void {
        $this->builder->call( 'updateField', [
            'label'         => 'Full Name',
            'name'          => 'full_name',
            'placeholder'   => 'Jane Doe',
            'help_text'     => 'As on your ID',
            'is_required'   => true,
            'default_value' => 'Jane',
            'width'         => 'half',
            'css_classes'   => 'name-field',
            'field_config'  => ['custom' => 'value'],
        ] )->assertHasNoErrors();

        expect( $this->field->fresh() )
            ->label->toBe( 'Full Name' )
            ->name->toBe( 'full_name' )
            ->placeholder->toBe( 'Jane Doe' )
            ->help_text->toBe( 'As on your ID' )
            ->is_required->toBeTrue()
            ->default_value->toBe( 'Jane' )
            ->width->toBe( 'half' )
            ->css_classes->toBe( 'name-field' )
            ->field_config->toBe( ['custom' => 'value'] );
    } );

    it( 'ignores attributes the builder changes through other actions', function (): void {
        $otherForm = Form::factory()->create();

        $this->builder->call( 'updateField', [
            'type'              => 'html',
            'form_id'           => $otherForm->id,
            'step_id'           => null,
            'uuid'              => 'hijacked',
            'sort_order'        => 99,
            'validation_rules'  => ['pattern' => '/x/'],
            'conditional_logic' => ['action' => 'hide', 'logic' => 'all', 'rules' => []],
        ] )->assertHasNoErrors();

        $field = $this->field->fresh();

        expect( $field->type )->toBe( 'text' )
            ->and( $field->form_id )->toBe( $this->form->id )
            ->and( $field->step_id )->toBe( $this->step->id )
            ->and( $field->uuid )->toBe( $this->field->uuid )
            ->and( $field->sort_order )->toBe( $this->field->sort_order )
            ->and( $field->validation_rules )->toBe( $this->field->validation_rules )
            ->and( $field->conditional_logic )->toBe( $this->field->conditional_logic );
    } );

    it( 'keeps the allowed attributes when disallowed ones are sent with them', function (): void {
        $this->builder->call( 'updateField', ['label' => 'Full Name', 'type' => 'html'] );

        expect( $this->field->fresh() )
            ->label->toBe( 'Full Name' )
            ->type->toBe( 'text' );
    } );

    it( 'rejects invalid values', function ( array $data, string $error ): void {
        $this->builder->call( 'updateField', $data )->assertHasErrors( $error );

        expect( $this->field->fresh()->label )->toBe( 'Name' );
    } )->with( [
        'unknown width'         => [['width' => 'enormous'], 'width'],
        'array label'           => [['label' => ['x']], 'label'],
        'blank name'            => [['name' => ''], 'name'],
        'string field config'   => [['field_config' => 'x'], 'field_config'],
        'non-boolean required'  => [['is_required' => 'maybe'], 'is_required'],
        'overlong placeholder'  => [['placeholder' => str_repeat( 'x', 256 )], 'placeholder'],
    ] );
} );
