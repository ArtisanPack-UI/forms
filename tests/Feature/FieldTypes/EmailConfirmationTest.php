<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Guards issue #111: the Email field's "Enable email confirmation" toggle.
 */
beforeEach( function (): void {
    $this->form  = Form::factory()->create( ['is_active' => true] );
    $this->email = FormField::factory()->for( $this->form )->required()->create( [
        'type'         => 'email',
        'name'         => 'email',
        'label'        => 'Email',
        'field_config' => ['confirm_email' => true],
    ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.forms.submissionData' );
} );

describe( 'settings', function (): void {
    it( 'offers the toggle and the sub-label settings', function (): void {
        expect( array_column( FieldSettings::schema( $this->email ), 'key' ) )->toContain( 'confirm_email', 'email_sub_label', 'confirm_sub_label', 'confirm_placeholder' );
    } );

    it( 'is off by default and never on for a read-only field', function (): void {
        expect( FormField::factory()->make( ['type' => 'email', 'name' => 'e'] )->requiresEmailConfirmation() )->toBeFalse()
            ->and( FormField::factory()->make( ['type' => 'email', 'name' => 'e', 'field_config' => ['confirm_email' => true, 'read_only' => true]] )->requiresEmailConfirmation() )->toBeFalse()
            ->and( $this->email->requiresEmailConfirmation() )->toBeTrue()
            ->and( $this->email->getConfirmationKey() )->toBe( 'email_confirmation' );
    } );

    it( 'adds a confirmed rule and a mismatch message only when on', function (): void {
        expect( $this->email->buildValidationRules() )->toContain( 'confirmed' )
            ->and( $this->email->buildValidationMessages() )->toHaveKey( 'confirmed' );

        $this->email->update( ['field_config' => []] );

        expect( $this->email->fresh()->buildValidationRules() )->not->toContain( 'confirmed' );
    } );
} );

describe( 'validation', function (): void {
    it( 'rejects a mismatched confirmation in Livewire', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.email', 'jane@example.com' )
            ->set( 'formData.email_confirmation', 'jane@example.org' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.email' => 'confirmed'] )
            ->assertSee( 'The email addresses entered for Email do not match.' );
    } );

    it( 'rejects a mismatched confirmation through the API', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'jane@example.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.email' );
    } );

    it( 'stores only the email when both match', function (): void {
        $seen = null;
        addFilter( 'ap.forms.submissionData', function ( array $data ) use ( &$seen ): array {
            $seen = $data;

            return $data;
        } );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'jane@example.com', 'email_confirmation' => 'jane@example.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        $values = $this->form->submissions()->first()->values;

        expect( $values )->toHaveCount( 1 )
            ->and( $values->first()->value )->toBe( 'jane@example.com' )
            ->and( $values->first()->value_array )->toBeNull()
            ->and( $seen )->toBe( ['email' => 'jane@example.com'] );
    } );

    it( 'keeps a real field that happens to use the confirmation key', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'email_confirmation', 'label' => 'Other'] );

        $data = app( SubmissionService::class )->withoutTransientInputs( $this->form->fields()->get(), ['email' => 'a@b.co', 'email_confirmation' => 'kept'] );

        expect( $data )->toBe( ['email' => 'a@b.co', 'email_confirmation' => 'kept'] );
    } );

    it( 'confirms a populated email with the same value', function (): void {
        $this->email->update( ['field_config' => ['confirm_email' => true, 'dynamic_population' => true]] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh(), 'prefill' => ['email' => 'jane@example.com']] )
            ->assertSet( 'formData.email', 'jane@example.com' )
            ->assertSet( 'formData.email_confirmation', 'jane@example.com' );
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders both inputs with sub-labels and without blocking paste', function (): void {
        $this->email->update( ['field_config' => ['confirm_email' => true, 'confirm_sub_label' => 'Repeat email']] );
        $html = FieldRenderer::render( $this->email->fresh(), '' );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( 'wire:model.live="formData.email"' )
            ->toContain( 'wire:model.live="formData.email_confirmation"' )
            ->toContain( 'Enter Email' )
            ->toContain( 'Repeat email' )
            ->not->toContain( 'onpaste' )
            ->not->toContain( '@paste' );
    } );

    it( 'renders one input when off', function (): void {
        $this->email->update( ['field_config' => []] );

        expect( FieldRenderer::render( $this->email->fresh(), '' ) )->not->toContain( 'email_confirmation' );
    } );

    it( 'sends the sub-labels to the React and Vue renderers', function (): void {
        $this->email->update( ['placeholder' => 'you@example.com'] );
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['email_confirmation'] )->toBe( ['email' => 'Enter Email', 'confirm' => 'Confirm Email', 'confirm_placeholder' => 'you@example.com'] );
    } );
} );
