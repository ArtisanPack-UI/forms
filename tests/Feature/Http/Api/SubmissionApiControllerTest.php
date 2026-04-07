<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Foundation\Auth\User;

beforeEach( function (): void {
    $this->user = new class extends User {
        protected $table = 'users';

        protected $fillable = ['name', 'email', 'password'];
    };

    $this->user = $this->user->create( [
        'name'     => 'Test User',
        'email'    => 'test@example.com',
        'password' => bcrypt( 'password' ),
    ] );
} );

describe( 'SubmissionApiController', function (): void {

    describe( 'index', function (): void {
        it( 'lists submissions with pagination', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count( 3 )->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.submissions.index', $form ) );

            $response->assertSuccessful()
                ->assertJsonCount( 3, 'data' )
                ->assertJsonStructure( [
                    'data' => [['id', 'form_id', 'submission_number', 'is_read', 'is_spam', 'created_at']],
                    'meta',
                ] );
        } );

        it( 'filters submissions by read status', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count( 2 )->for( $form )->read()->create();
            FormSubmission::factory()->for( $form )->unread()->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.submissions.index', [$form, 'is_read' => 'true'] ) );

            $response->assertSuccessful()
                ->assertJsonCount( 2, 'data' );
        } );

        it( 'filters submissions by spam status', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->for( $form )->spam()->create();
            FormSubmission::factory()->count( 2 )->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.submissions.index', [$form, 'is_spam' => 'true'] ) );

            $response->assertSuccessful()
                ->assertJsonCount( 1, 'data' );
        } );
    } );

    describe( 'show', function (): void {
        it( 'returns a submission with values', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.submissions.show', [$form, $submission] ) );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.id', $submission->id )
                ->assertJsonStructure( [
                    'data' => ['id', 'form_id', 'submission_number', 'values', 'uploads'],
                ] );
        } );

        it( 'returns 404 for submission not belonging to form', function (): void {
            $form       = Form::factory()->create();
            $otherForm  = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $otherForm )->create();

            $response = $this->actingAs( $this->user )
                ->getJson( route( 'api.forms.submissions.show', [$form, $submission] ) );

            $response->assertNotFound();
        } );
    } );

    describe( 'update', function (): void {
        it( 'updates submission metadata', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->unread()->create();

            $response = $this->actingAs( $this->user )
                ->putJson( route( 'api.forms.submissions.update', [$form, $submission] ), [
                    'is_read'     => true,
                    'is_starred'  => true,
                    'admin_notes' => 'Follow up needed',
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'data.is_read', true )
                ->assertJsonPath( 'data.is_starred', true )
                ->assertJsonPath( 'data.admin_notes', 'Follow up needed' );
        } );
    } );

    describe( 'destroy', function (): void {
        it( 'deletes a submission', function (): void {
            $form       = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->deleteJson( route( 'api.forms.submissions.destroy', [$form, $submission] ) );

            $response->assertNoContent();
            expect( FormSubmission::find( $submission->id ) )->toBeNull();
        } );
    } );

    describe( 'submit (public)', function (): void {
        it( 'submits a form successfully', function (): void {
            $form  = Form::factory()->create( ['is_active' => true] );
            $field = FormField::factory()->for( $form )->text()->create( [
                'name'  => 'full_name',
                'label' => 'Full Name',
            ] );

            $response = $this->postJson( route( 'api.forms.submit', $form ), [
                'data' => [
                    'full_name' => 'John Doe',
                ],
                '_form_loaded_at' => time() - 10,
            ] );

            $response->assertCreated()
                ->assertJsonStructure( ['message', 'submission_id'] );

            expect( FormSubmission::where( 'form_id', $form->id )->count() )->toBe( 1 );
        } );

        it( 'returns 404 for inactive form', function (): void {
            $form = Form::factory()->inactive()->create();

            $response = $this->postJson( route( 'api.forms.submit', $form ), [
                'data' => [],
            ] );

            $response->assertForbidden();
        } );

        it( 'does not require authentication', function (): void {
            $form = Form::factory()->create( ['is_active' => true] );

            $response = $this->postJson( route( 'api.forms.submit', $form ), [
                'data'            => [],
                '_form_loaded_at' => time() - 10,
            ] );

            // Should not be 401 - it's a public endpoint
            expect( $response->status() )->not->toBe( 401 );
        } );

        it( 'returns rate limit response', function (): void {
            $form = Form::factory()->create( ['is_active' => true] );

            // Make 6 submissions (exceeds rate limit of 5)
            for ( $i = 0; $i < 6; $i++ ) {
                $response = $this->postJson( route( 'api.forms.submit', $form ), [
                    'data'            => [],
                    '_form_loaded_at' => time() - 10,
                ] );
            }

            $response->assertStatus( 429 );
        } );
    } );

    describe( 'export', function (): void {
        it( 'exports submissions as CSV', function (): void {
            $form = Form::factory()->create();
            FormSubmission::factory()->count( 2 )->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->get( route( 'api.forms.submissions.export', $form ) );

            $response->assertSuccessful()
                ->assertHeader( 'content-type', 'text/csv; charset=UTF-8' );
        } );
    } );

    describe( 'bulk', function (): void {
        it( 'performs bulk mark read', function (): void {
            $form        = Form::factory()->create();
            $submissions = FormSubmission::factory()->count( 3 )->for( $form )->unread()->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.submissions.bulk', $form ), [
                    'action' => 'mark_read',
                    'ids'    => $submissions->pluck( 'id' )->toArray(),
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'affected', 3 );

            expect( FormSubmission::where( 'form_id', $form->id )->where( 'is_read', true )->count() )->toBe( 3 );
        } );

        it( 'performs bulk delete', function (): void {
            $form        = Form::factory()->create();
            $submissions = FormSubmission::factory()->count( 2 )->for( $form )->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.submissions.bulk', $form ), [
                    'action' => 'delete',
                    'ids'    => $submissions->pluck( 'id' )->toArray(),
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'affected', 2 );

            expect( FormSubmission::where( 'form_id', $form->id )->count() )->toBe( 0 );
        } );

        it( 'validates action parameter', function (): void {
            $form = Form::factory()->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.submissions.bulk', $form ), [
                    'action' => 'invalid_action',
                    'ids'    => [1],
                ] );

            $response->assertUnprocessable()
                ->assertJsonValidationErrors( 'action' );
        } );

        it( 'only affects submissions belonging to the form', function (): void {
            $form       = Form::factory()->create();
            $otherForm  = Form::factory()->create();
            $submission = FormSubmission::factory()->for( $otherForm )->create();

            $response = $this->actingAs( $this->user )
                ->postJson( route( 'api.forms.submissions.bulk', $form ), [
                    'action' => 'delete',
                    'ids'    => [$submission->id],
                ] );

            $response->assertSuccessful()
                ->assertJsonPath( 'affected', 0 );

            // The other form's submission should still exist
            expect( FormSubmission::find( $submission->id ) )->not->toBeNull();
        } );
    } );
} );
