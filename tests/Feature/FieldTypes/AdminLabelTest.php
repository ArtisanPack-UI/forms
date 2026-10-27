<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\SubmissionDetail;
use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #97: an admin-only field label for submissions and exports.
 */
describe( 'admin field label', function (): void {
    beforeEach( function (): void {
        $this->form   = Form::factory()->create();
        $this->source = FormField::factory()->for( $this->form )->create( [
            'type'         => 'text',
            'name'         => 'source',
            'label'        => 'How did you hear about us?',
            'sort_order'   => 1,
            'field_config' => ['admin_label' => 'Source'],
        ] );
        $this->email = FormField::factory()->for( $this->form )->create( [
            'type'       => 'email',
            'name'       => 'email',
            'label'      => 'Email',
            'sort_order' => 2,
        ] );
    } );

    it( 'is an Advanced tab setting stored in field_config', function (): void {
        expect( FieldSettings::find( $this->source, 'admin_label' ) )->toMatchArray( ['tab' => 'advanced', 'store' => 'field_config', 'input' => 'text'] )
            ->and( FieldSettings::find( FormField::factory()->make( ['type' => 'divider'] ), 'admin_label' ) )->toBeNull();
    } );

    it( 'falls back to the label when blank', function (): void {
        $blank = FormField::factory()->make( ['label' => 'Email', 'field_config' => ['admin_label' => '   ']] );

        expect( $this->source->getAdminLabel() )->toBe( 'Source' )
            ->and( $this->email->getAdminLabel() )->toBe( 'Email' )
            ->and( $blank->getAdminLabel() )->toBe( 'Email' )
            ->and( $blank->getStoredAdminLabel() )->toBeNull();
    } );

    it( 'caps a stored admin label at the column length', function (): void {
        $long = FormField::factory()->make( ['field_config' => ['admin_label' => str_repeat( 'é', 300 )]] );

        expect( $long->getStoredAdminLabel() )->toBe( str_repeat( 'é', 255 ) );
    } );

    it( 'is denormalized into the submission values alongside the label', function (): void {
        expect( Schema::hasColumn( 'form_submission_values', 'field_admin_label' ) )->toBeTrue();

        $submission = app( SubmissionService::class )->create( $this->form, ['source' => 'A friend', 'email' => 'ada@example.com'] );
        $values     = $submission->values()->get()->keyBy( 'field_name' );

        expect( $values['source']->field_label )->toBe( 'How did you hear about us?' )
            ->and( $values['source']->field_admin_label )->toBe( 'Source' )
            ->and( $values['email']->field_admin_label )->toBeNull();
    } );

    it( 'labels submission values by their admin label, then label, then name', function (): void {
        $admin   = FormSubmissionValue::factory()->make( ['field_label' => 'Long question?', 'field_admin_label' => 'Short', 'field_name' => 'q'] );
        $label   = FormSubmissionValue::factory()->make( ['field_label' => 'Long question?', 'field_admin_label' => null, 'field_name' => 'q'] );
        $unnamed = FormSubmissionValue::factory()->make( ['field_label' => '', 'field_admin_label' => null, 'field_name' => 'q'] );

        expect( $admin->display_label )->toBe( 'Short' )
            ->and( $label->display_label )->toBe( 'Long question?' )
            ->and( $unnamed->display_label )->toBe( 'q' );
    } );

    it( 'heads its export column', function (): void {
        $headers = app( ExportService::class )->buildHeaders( $this->form );

        expect( $headers )->toContain( 'Source', 'Email' )
            ->and( $headers )->not->toContain( 'How did you hear about us?' );
    } );

    it( 'labels the submission detail view', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['source' => 'A friend', 'email' => 'ada@example.com'] );

        Livewire::actingAs( User::factory()->create() )->test( SubmissionDetail::class, ['submission' => $submission] )
            ->assertSee( 'Source' )
            ->assertDontSee( 'How did you hear about us?' );
    } );

    it( 'labels admin notifications but not the autoresponder the visitor reads', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['source' => 'A friend', 'email' => 'ada@example.com'] );
        $service    = app( NotificationService::class );

        expect( $service->formatAllFieldsAsTable( $submission ) )->toContain( 'Source' )
            ->and( $service->formatAllFieldsPlainText( $submission ) )->toContain( 'Source: A friend' )
            ->and( $service->formatAllFieldsPlainText( $submission, false ) )->toContain( 'How did you hear about us?: A friend' );

        $admin         = FormNotification::factory()->for( $this->form )->create( ['type' => FormNotification::TYPE_ADMIN, 'message' => '{all_fields}'] );
        $autoresponder = FormNotification::factory()->for( $this->form )->create( ['type' => FormNotification::TYPE_AUTORESPONDER, 'message' => '{all_fields}'] );

        $adminMail = new FormSubmissionNotification( $admin, $submission );
        $replyMail = new FormSubmissionNotification( $autoresponder, $submission );

        expect( $adminMail->submissionDataTable )->toContain( 'Source' )
            ->and( $adminMail->parsedMessage )->toContain( 'Source: A friend' )
            ->and( $replyMail->submissionDataTable )->not->toContain( '>Source<' )
            ->and( $replyMail->parsedMessage )->toContain( 'How did you hear about us?: A friend' );
    } );
} );
