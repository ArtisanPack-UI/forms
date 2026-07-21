<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Requests\StoreFormRequest;
use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\IntegrationService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FiresValidationHooks;
use Illuminate\Foundation\Http\FormRequest;

class FiresValidationHooksTestRequest extends FormRequest
{
    use FiresValidationHooks;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }
}

class FiresValidationHooksOverrideRequest extends FormRequest
{
    use FiresValidationHooks;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }

    protected function prepareForValidation(): void
    {
        $this->fireBeforeValidate();
    }

    protected function passedValidation(): void
    {
        $this->fireValidated();
    }
}

beforeEach( function (): void {
    if ( ! function_exists( 'removeAllFilters' ) ) {
        $this->markTestSkipped( 'Hook system not available' );
    }

    foreach ( [
        'ap.forms.beforeValidate',
        'ap.forms.validated',
        'ap.forms.fieldRender',
        'ap.forms.integrationRegistered',
        'ap.forms.notificationSubject',
        'ap.forms.notificationBody',
    ] as $hook ) {
        removeAllFilters( $hook );
    }
} );

describe( 'Wave 5 hooks', function (): void {
    describe( 'ap.forms.beforeValidate action', function (): void {
        it( 'fires before FormRequest validation runs', function (): void {
            $captured = null;

            addAction( 'ap.forms.beforeValidate', function ( $request ) use ( &$captured ): void {
                $captured = $request;
            } );

            $request = FiresValidationHooksTestRequest::create( '/test', 'POST', [
                'name' => 'Sample Form',
            ] );
            $request->setContainer( app() )->setRedirector( app( 'redirect' ) );
            $request->validateResolved();

            expect( $captured )->toBeInstanceOf( FiresValidationHooksTestRequest::class );
        } );
    } );

    describe( 'ap.forms.validated action', function (): void {
        it( 'fires after FormRequest validation succeeds with the validated payload', function (): void {
            $capturedRequest   = null;
            $capturedValidated = null;

            addAction( 'ap.forms.validated', function ( $request, array $validated ) use ( &$capturedRequest, &$capturedValidated ): void {
                $capturedRequest   = $request;
                $capturedValidated = $validated;
            } );

            $request = FiresValidationHooksTestRequest::create( '/test', 'POST', [
                'name' => 'Sample Form',
            ] );
            $request->setContainer( app() )->setRedirector( app( 'redirect' ) );
            $request->validateResolved();

            expect( $capturedRequest )->toBeInstanceOf( FiresValidationHooksTestRequest::class );
            expect( $capturedValidated )->toBe( ['name' => 'Sample Form'] );
        } );
    } );

    describe( 'ap.forms.fieldRender filter', function (): void {
        it( 'allows integration packages to mutate the rendered field HTML', function (): void {
            $field = FormField::factory()->create( [
                'type'  => 'text',
                'label' => 'First Name',
                'name'  => 'first_name',
            ] );

            addFilter( 'ap.forms.fieldRender', function ( string $html, FormField $filtered ): string {
                return '<div data-wrapped="' . $filtered->name . '">' . $html . '</div>';
            } );

            $rendered = FieldRenderer::render( $field );

            expect( $rendered )->toContain( 'data-wrapped="first_name"' );
        } );

        it( 'passes the field instance to subscribers', function (): void {
            $field    = FormField::factory()->create( ['type' => 'text'] );
            $received = null;

            addFilter( 'ap.forms.fieldRender', function ( string $html, FormField $filtered ) use ( &$received ): string {
                $received = $filtered;

                return $html;
            } );

            FieldRenderer::render( $field );

            expect( $received )->not->toBeNull();
            expect( $received->id )->toBe( $field->id );
        } );
    } );

    describe( 'ap.forms.integrationRegistered action', function (): void {
        it( 'fires with the slug and config passed to registerIntegration()', function (): void {
            $slug   = null;
            $config = null;

            addAction( 'ap.forms.integrationRegistered', function ( string $capturedSlug, array $capturedConfig ) use ( &$slug, &$config ): void {
                $slug   = $capturedSlug;
                $config = $capturedConfig;
            } );

            app( IntegrationService::class )->registerIntegration( 'mailchimp', [
                'label' => 'Mailchimp',
                'icon'  => 'o-envelope',
            ] );

            expect( $slug )->toBe( 'mailchimp' );
            expect( $config )->toBe( ['label' => 'Mailchimp', 'icon' => 'o-envelope'] );
        } );
    } );

    describe( 'ap.forms.notificationSubject filter', function (): void {
        it( 'lets subscribers rewrite the outgoing email subject', function (): void {
            $notification = FormNotification::factory()->create( [
                'subject' => 'New submission',
                'message' => 'Hello',
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $capturedNotification = null;
            $capturedSubmission   = null;

            addFilter(
                'ap.forms.notificationSubject',
                function ( string $subject, FormNotification $n, FormSubmission $s ) use ( &$capturedNotification, &$capturedSubmission ): string {
                    $capturedNotification = $n;
                    $capturedSubmission   = $s;

                    return '[PREFIX] ' . $subject;
                },
            );

            $mail     = new FormSubmissionNotification( $notification, $submission );
            $envelope = $mail->envelope();

            expect( $envelope->subject )->toBe( '[PREFIX] New submission' );
            expect( $capturedNotification?->id )->toBe( $notification->id );
            expect( $capturedSubmission?->id )->toBe( $submission->id );
        } );
    } );

    describe( 'ap.forms.notificationBody filter', function (): void {
        it( 'lets subscribers rewrite the outgoing email body', function (): void {
            $notification = FormNotification::factory()->create( [
                'subject' => 'Hi',
                'message' => 'Original body',
            ] );
            $submission = FormSubmission::factory()->for( $notification->form )->create();

            $capturedNotification = null;
            $capturedSubmission   = null;

            addFilter(
                'ap.forms.notificationBody',
                function ( string $body, FormNotification $n, FormSubmission $s ) use ( &$capturedNotification, &$capturedSubmission ): string {
                    $capturedNotification = $n;
                    $capturedSubmission   = $s;

                    return $body . ' (appended)';
                },
            );

            $mail = new FormSubmissionNotification( $notification, $submission );

            $reflection = new ReflectionProperty( $mail, 'parsedMessage' );
            expect( $reflection->getValue( $mail ) )->toBe( 'Original body (appended)' );
            expect( $capturedNotification?->id )->toBe( $notification->id );
            expect( $capturedSubmission?->id )->toBe( $submission->id );
        } );
    } );

    describe( 'trait shadowing', function (): void {
        it( 'still fires both hooks when the FormRequest overrides both lifecycle methods and delegates to the trait helpers', function (): void {
            $beforeFired    = 0;
            $validatedFired = 0;

            addAction( 'ap.forms.beforeValidate', function () use ( &$beforeFired ): void {
                $beforeFired++;
            } );

            addAction( 'ap.forms.validated', function () use ( &$validatedFired ): void {
                $validatedFired++;
            } );

            $request = FiresValidationHooksOverrideRequest::create( '/test', 'POST', [
                'name' => 'Sample',
            ] );
            $request->setContainer( app() )->setRedirector( app( 'redirect' ) );
            $request->validateResolved();

            expect( $beforeFired )->toBe( 1 );
            expect( $validatedFired )->toBe( 1 );
        } );

        it( 'fires ap.forms.beforeValidate from a real overriding FormRequest (StoreFormRequest)', function (): void {
            // StoreFormRequest overrides prepareForValidation() to normalize slug — the trait
            // method would be silently shadowed if the override didn't delegate to fireBeforeValidate().
            $captured = null;

            addAction( 'ap.forms.beforeValidate', function ( $request ) use ( &$captured ): void {
                $captured = $request;
            } );

            $request = StoreFormRequest::create( '/forms', 'POST', [
                'name' => 'Sample Form',
            ] );
            $request->setContainer( app() )->setRedirector( app( 'redirect' ) );

            // Skip authorize()/validate() and drive prepareForValidation directly — the
            // hook must fire regardless of downstream authorization outcome.
            $reflection = new ReflectionMethod( $request, 'prepareForValidation' );
            $reflection->invoke( $request );

            expect( $captured )->toBeInstanceOf( StoreFormRequest::class );
        } );
    } );
} );
