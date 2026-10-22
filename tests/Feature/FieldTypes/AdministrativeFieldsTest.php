<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #102: administrative fields, which are left out of the public
 * form and filled in by admins on the submission.
 */
describe( 'visibility setting', function (): void {
    it( 'is an Advanced tab select defaulting to visible', function (): void {
        expect( FieldSettings::find( FormField::factory()->make( ['type' => 'select'] ), 'visibility' ) )
            ->toMatchArray( ['tab' => 'advanced', 'input' => 'select', 'default' => 'visible'] );
    } );

    it( 'is not offered on file and layout fields', function ( string $type ): void {
        expect( FieldSettings::find( FormField::factory()->make( ['type' => $type] ), 'visibility' ) )->toBeNull();
    } )->with( ['file', 'paragraph'] );

    it( 'marks a field administrative only when the type offers the setting', function (): void {
        expect( FormField::factory()->make( ['type' => 'text', 'field_config' => ['visibility' => 'administrative']] )->isAdministrative() )->toBeTrue()
            ->and( FormField::factory()->make( ['type' => 'text', 'field_config' => ['visibility' => 'visible']] )->isAdministrative() )->toBeFalse()
            ->and( FormField::factory()->make( ['type' => 'file', 'field_config' => ['visibility' => 'administrative']] )->isAdministrative() )->toBeFalse();
    } );
} );

describe( 'administrative fields on the public form', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'label' => 'Your Name'] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'select',
            'name'         => 'status',
            'label'        => 'Follow-up Status',
            'is_required'  => true,
            'sort_order'   => 2,
            'field_config' => ['visibility' => 'administrative', 'options' => [['value' => 'open', 'label' => 'Open'], ['value' => 'done', 'label' => 'Done']]],
        ] );
    } );

    it( 'are not rendered or validated by the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSee( 'Your Name' )
            ->assertDontSee( 'Follow-up Status' )
            ->assertSet( 'formData', ['name' => ''] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.name', 'Ada' )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );
    } );

    it( 'drop a value a visitor submits for them', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.name', 'Ada' )
            ->set( 'formData.status', 'done' )
            ->call( 'submit' );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['name' => 'Grace', 'status' => 'done'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        foreach ( $this->form->submissions as $submission ) {
            expect( $submission->getValue( 'status' ) )->toBeNull();
        }
    } );

    it( 'are left out of the render payload, including step fields', function (): void {
        $step = FormStep::factory()->for( $this->form )->create();
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'text',
            'name'         => 'internal',
            'step_id'      => $step->id,
            'field_config' => ['visibility' => 'administrative'],
        ] );

        $payload = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) );
        $steps   = $payload['steps']->resolve();

        expect( array_column( $payload['fields']->resolve(), 'name' ) )->toBe( ['name'] )
            ->and( $steps[0]['fields']->resolve() )->toBe( [] );

        $this->getJson( route( 'api.forms.render', $this->form ) )
            ->assertOk()
            ->assertJsonMissing( ['name' => 'status'] );
    } );
} );

describe( 'editing administrative fields on the submission', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'name', 'label' => 'Your Name'] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'textarea',
            'name'         => 'notes',
            'label'        => 'Internal Notes',
            'sort_order'   => 2,
            'field_config' => ['visibility' => 'administrative'],
        ] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'radio',
            'name'         => 'status',
            'label'        => 'Follow-up Status',
            'is_required'  => true,
            'sort_order'   => 3,
            'field_config' => ['visibility' => 'administrative', 'enable_other' => true, 'options' => [['value' => 'open', 'label' => 'Open']]],
        ] );

        $this->submission = app( SubmissionService::class )->create( $this->form, ['name' => 'Ada'] );
        $this->user       = User::factory()->create( ['is_admin' => true] );
    } );

    it( 'shows the fields in the submission detail and saves them', function (): void {
        $this->actingAs( $this->user );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertSee( 'Administrative Fields' )
            ->assertSee( 'Internal Notes' )
            ->set( 'formData.notes', 'Called back.' )
            ->set( 'formData.status', 'open' )
            ->call( 'saveAdministrativeFields' )
            ->assertHasNoErrors()
            ->assertSet( 'administrativeFieldsSaved', true );

        expect( $this->submission->fresh()->getValue( 'notes' ) )->toBe( 'Called back.' )
            ->and( $this->submission->fresh()->getValue( 'status' ) )->toBe( 'Open' );
    } );

    it( 'validates the fields with their own rules', function (): void {
        $this->actingAs( $this->user );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->call( 'saveAdministrativeFields' )
            ->assertHasErrors( 'formData.status' )
            ->assertSet( 'administrativeFieldsSaved', false );
    } );

    it( 'saves the text of an "Other" choice and reloads it into the input', function (): void {
        $this->actingAs( $this->user );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'formData.status', '__other__' )
            ->set( 'otherValues.status', 'Waiting on legal' )
            ->call( 'saveAdministrativeFields' )
            ->assertHasNoErrors();

        expect( $this->submission->fresh()->getValue( 'status' ) )->toBe( 'Other: Waiting on legal' );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission->fresh()] )
            ->assertSet( 'formData.status', '__other__' )
            ->assertSet( 'otherValues.status', 'Waiting on legal' );
    } );

    it( 'replaces an earlier value and removes it when cleared', function (): void {
        $service = app( SubmissionService::class );

        $service->updateAdministrativeValues( $this->submission, ['notes' => 'First'] );
        $service->updateAdministrativeValues( $this->submission, ['notes' => 'Second', 'name' => 'Tampered'] );

        expect( $this->submission->values()->where( 'field_name', 'notes' )->pluck( 'value' )->all() )->toBe( ['Second'] )
            ->and( $this->submission->fresh()->getValue( 'name' ) )->toBe( 'Ada' );

        $service->updateAdministrativeValues( $this->submission, ['notes' => ''] );

        expect( $this->submission->values()->where( 'field_name', 'notes' )->exists() )->toBeFalse();
    } );

    it( 'returns the fields with the submission and saves them through the API', function (): void {
        $this->actingAs( $this->user )
            ->getJson( route( 'api.forms.submissions.show', [$this->form, $this->submission] ) )
            ->assertOk()
            ->assertJsonCount( 2, 'data.administrative_fields' )
            ->assertJsonPath( 'data.administrative_fields.0.name', 'notes' )
            ->assertJsonPath( 'data.administrative_fields.1.other_choice_label', 'Other' );

        $this->actingAs( $this->user )
            ->putJson( route( 'api.forms.submissions.update', [$this->form, $this->submission] ), [
                'admin_values' => ['notes' => 'Follow up Monday', 'status' => 'open'],
            ] )
            ->assertOk();

        expect( $this->submission->fresh()->getValue( 'notes' ) )->toBe( 'Follow up Monday' );
    } );

    it( 'rejects invalid values through the API', function (): void {
        $this->actingAs( $this->user )
            ->putJson( route( 'api.forms.submissions.update', [$this->form, $this->submission] ), [
                'admin_values' => ['notes' => 'Missing the status'],
            ] )
            ->assertUnprocessable()
            ->assertJsonValidationErrors( 'admin_values.status' );
    } );

    it( 'still updates the metadata alone', function (): void {
        $this->actingAs( $this->user )
            ->putJson( route( 'api.forms.submissions.update', [$this->form, $this->submission] ), ['is_starred' => true] )
            ->assertOk();

        expect( $this->submission->fresh()->is_starred )->toBeTrue();
    } );

    it( 'does not let the client point the component at another submission', function (): void {
        $this->actingAs( $this->user );
        $other = app( SubmissionService::class )->create( $this->form, ['name' => 'Grace'] );

        expect( fn () => Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )->set( 'submissionId', $other->id ) )
            ->toThrow( CannotUpdateLockedPropertyException::class );
    } );

    it( 'requires the update ability to save', function (): void {
        config( ['artisanpack.forms.authorization.restrict_by_owner' => true] );
        $owner = User::factory()->create();
        $this->form->update( ['user_id' => $owner->id] );

        $this->actingAs( User::factory()->create( ['is_admin' => false] ) );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->set( 'formData.notes', 'Not mine' )
            ->set( 'formData.status', 'open' )
            ->call( 'saveAdministrativeFields' )
            ->assertForbidden();

        expect( $this->submission->values()->where( 'field_name', 'notes' )->exists() )->toBeFalse();
    } );

    it( 'leaves out masked fields for users who may not reveal them', function (): void {
        $this->form->fields()->where( 'name', 'notes' )->first()->update( ['field_config' => ['visibility' => 'administrative', 'mask_in_admin' => true]] );
        app( SubmissionService::class )->updateAdministrativeValues( $this->submission, ['notes' => 'Sensitive history'] );
        $staff = User::factory()->create( ['is_admin' => false] );

        $this->actingAs( $staff )
            ->getJson( route( 'api.forms.submissions.show', [$this->form, $this->submission] ) )
            ->assertJsonCount( 1, 'data.administrative_fields' );

        $this->actingAs( $staff );

        Livewire::test( SubmissionDetail::class, ['submission' => $this->submission] )
            ->assertSet( 'formData', ['status' => ''] )
            ->assertDontSee( 'Sensitive history' );

        $this->putJson( route( 'api.forms.submissions.update', [$this->form, $this->submission] ), [
            'admin_values' => ['notes' => 'Overwritten', 'status' => 'open'],
        ] )->assertOk();

        expect( $this->submission->values()->where( 'field_name', 'notes' )->value( 'value' ) )->toBe( 'Sensitive history' );
    } );
} );
