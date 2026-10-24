<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Captcha\CaptchaManager;
use ArtisanPackUI\Forms\Captcha\CaptchaResult;
use ArtisanPackUI\Forms\Captcha\CaptchaVerifier;
use ArtisanPackUI\Forms\Captcha\HCaptchaDriver;
use ArtisanPackUI\Forms\Captcha\RecaptchaV2Driver;
use ArtisanPackUI\Forms\Captcha\RecaptchaV3Driver;
use ArtisanPackUI\Forms\Captcha\TurnstileDriver;
use ArtisanPackUI\Forms\Contracts\CaptchaDriver;
use ArtisanPackUI\Forms\FieldTypes\CaptchaFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Guards issue #113: the CAPTCHA field, its Turnstile, hCaptcha and
 * reCAPTCHA v2/v3 drivers, and server-side token verification in the
 * Livewire and API submission pipelines.
 */
beforeEach( function (): void {
    config()->set( 'artisanpack.forms.spam_protection.captcha.drivers', [
        'turnstile'    => ['site_key' => 'ts-site', 'secret_key' => 'ts-secret'],
        'hcaptcha'     => ['site_key' => 'hc-site', 'secret_key' => 'hc-secret'],
        'recaptcha_v2' => ['site_key' => 'rc2-site', 'secret_key' => 'rc2-secret'],
        'recaptcha_v3' => ['site_key' => 'rc3-site', 'secret_key' => 'rc3-secret', 'score_threshold' => 0.5, 'action' => 'forms_submit'],
    ] );

    $this->form    = Form::factory()->create( ['is_active' => true] );
    $this->email   = FormField::factory()->for( $this->form )->required()->create( ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'sort_order' => 1] );
    $this->captcha = FormField::factory()->for( $this->form )->create( ['type' => 'captcha', 'name' => 'verify', 'label' => 'Verification', 'sort_order' => 2] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.forms.captchaDrivers' );
} );

/**
 * Fakes a provider's siteverify answer.
 *
 * @param  array<string, mixed>  $body  The answer.
 */
function fakeSiteVerify( array $body ): void
{
    Http::fake( ['*' => Http::response( $body )] );
}

describe( 'drivers', function (): void {
    it( 'registers Turnstile, hCaptcha and reCAPTCHA v2 and v3', function (): void {
        $drivers = app( CaptchaManager::class )->drivers();

        expect( array_keys( $drivers ) )->toBe( ['turnstile', 'hcaptcha', 'recaptcha_v2', 'recaptcha_v3'] )
            ->and( $drivers['turnstile'] )->toBeInstanceOf( TurnstileDriver::class )
            ->and( app( CaptchaManager::class )->driver() )->toBeInstanceOf( TurnstileDriver::class );
    } );

    it( 'lets an app register a driver through the filter', function (): void {
        $custom = new class implements CaptchaDriver {
            public function key(): string
            {
            return 'custom';
            }

            public function label(): string
            {
            return 'Custom';
            }

            public function isConfigured(): bool
            {
            return true;
            }

            public function sizes(): array
            {
            return ['normal'];
            }

            public function widget( string $size, string $theme ): array
            {
            return ['provider' => 'custom', 'site_key' => 'x', 'script' => 'https://example.com/c.js', 'size' => 'normal', 'theme' => 'auto'];
            }

            public function verify( string $token, ?string $ipAddress = null ): CaptchaResult
            {
            return 'good' === $token ? CaptchaResult::pass() : CaptchaResult::fail( ['bad'] );
            }
        };

        addFilter( 'ap.forms.captchaDrivers', function ( array $drivers ) use ( $custom ): array {
            $drivers['custom'] = $custom;
            $drivers['broken'] = stdClass::class;

            return $drivers;
        } );

        expect( app( CaptchaManager::class )->drivers() )->toHaveKey( 'custom' )->not->toHaveKey( 'broken' )
            ->and( array_column( FieldSettings::find( $this->captcha, 'captcha_provider' )['options'], 'value' ) )->toContain( '', 'turnstile', 'custom' );
    } );

    it( 'posts the secret, token and IP to each provider', function ( string $driver, string $url ): void {
        fakeSiteVerify( ['success' => true, 'score' => 0.9, 'action' => 'forms_submit'] );

        expect( app( CaptchaManager::class )->driver( $driver )->verify( 'token-1', '203.0.113.9' )->passed )->toBeTrue();

        Http::assertSent( fn ( HttpRequest $request ): bool => str_starts_with( $request->url(), $url )
            && 'token-1' === $request['response']
            && '203.0.113.9' === $request['remoteip']
            && str_ends_with( (string) $request['secret'], '-secret' ) );
    } )->with( [
        'turnstile'    => ['turnstile', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'],
        'hcaptcha'     => ['hcaptcha', 'https://api.hcaptcha.com/siteverify'],
        'recaptcha v2' => ['recaptcha_v2', 'https://www.google.com/recaptcha/api/siteverify'],
        'recaptcha v3' => ['recaptcha_v3', 'https://www.google.com/recaptcha/api/siteverify'],
    ] );

    it( 'sends the site key to hCaptcha', function (): void {
        fakeSiteVerify( ['success' => true] );

        ( new HCaptchaDriver() )->verify( 'token' );

        Http::assertSent( fn ( HttpRequest $request ): bool => 'hc-site' === $request['sitekey'] );
    } );

    it( 'fails with the provider\'s error codes', function (): void {
        fakeSiteVerify( ['success' => false, 'error-codes' => ['timeout-or-duplicate']] );

        $result = ( new RecaptchaV2Driver() )->verify( 'token' );

        expect( $result->passed )->toBeFalse()->and( $result->errorCodes )->toBe( ['timeout-or-duplicate'] );
    } );

    it( 'fails closed when the provider cannot be reached', function (): void {
        Http::fake( fn () => throw new ConnectionException( 'down' ) );

        expect( ( new TurnstileDriver() )->verify( 'token' )->errorCodes )->toBe( ['request-failed'] );
    } );

    it( 'checks the reCAPTCHA v3 score and action', function (): void {
        $driver = new RecaptchaV3Driver();

        Http::fake( ['*' => Http::sequence()
            ->push( ['success' => true, 'score' => 0.3, 'action' => 'forms_submit'] )
            ->push( ['success' => true, 'score' => 0.9, 'action' => 'login'] )
            ->push( ['success' => true, 'score' => 0.7, 'action' => 'forms_submit'] )] );

        expect( $driver->verify( 'token' )->errorCodes )->toBe( ['score-too-low'] )
            ->and( $driver->verify( 'token' )->errorCodes )->toBe( ['action-mismatch'] )
            ->and( $driver->verify( 'token' ) )->passed->toBeTrue()->score->toBe( 0.7 );
    } );

    it( 'is not configured without both keys, and never calls the provider then', function (): void {
        Http::fake();

        $driver = new TurnstileDriver( ['site_key' => 'only-site'] );

        expect( $driver->isConfigured() )->toBeFalse()
            ->and( $driver->verify( 'token' )->passed )->toBeFalse();

        Http::assertNothingSent();
    } );
} );

describe( 'field', function (): void {
    it( 'exposes only public widget settings', function (): void {
        $widget = CaptchaFieldType::widget( $this->captcha );

        expect( $widget )->toBe( [
            'provider' => 'turnstile',
            'site_key' => 'ts-site',
            'script'   => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
            'size'     => 'normal',
            'theme'    => 'auto',
        ] )
            ->and( json_encode( $widget ) )->not->toContain( 'secret' );
    } );

    it( 'uses the field\'s provider and size, falling back to a size the provider supports', function (): void {
        $this->captcha->update( ['field_config' => ['captcha_provider' => 'recaptcha_v2', 'captcha_size' => 'invisible', 'captcha_theme' => 'dark']] );

        expect( CaptchaFieldType::widget( $this->captcha->fresh() ) )->toMatchArray( ['provider' => 'recaptcha_v2', 'size' => 'invisible', 'theme' => 'dark'] );

        $this->captcha->update( ['field_config' => ['captcha_provider' => 'turnstile', 'captcha_size' => 'invisible']] );

        expect( CaptchaFieldType::widget( $this->captcha->fresh() )['size'] )->toBe( 'normal' );

        $this->captcha->update( ['field_config' => ['captcha_provider' => 'recaptcha_v3']] );

        expect( CaptchaFieldType::widget( $this->captcha->fresh() ) )->toMatchArray( [
            'size'   => 'invisible',
            'action' => 'forms_submit',
            'script' => 'https://www.google.com/recaptcha/api.js?render=rc3-site',
        ] );
    } );

    it( 'requires a token only while the provider has keys', function (): void {
        expect( $this->captcha->buildValidationRules() )->toBe( ['required', 'string', 'max:4096'] );

        config()->set( 'artisanpack.forms.spam_protection.captcha.drivers.turnstile.secret_key', null );

        expect( $this->captcha->fresh()->buildValidationRules() )->toBe( [] )
            ->and( FieldRenderer::render( $this->captcha->fresh() ) )->not->toContain( 'formsCaptcha' );
    } );

    it( 'is never exported, tagged or offered to conditional logic', function (): void {
        expect( app( ExportService::class )->buildHeaders( $this->form ) )->not->toContain( 'Verification' )
            ->and( $this->captcha->fieldType()->mergeTags( $this->captcha ) )->toBe( [] )
            ->and( app( ConditionalLogicService::class )->getAvailableConditionTargets( $this->email, $this->form->fields()->get() )->pluck( 'name' )->all() )->not->toContain( 'verify' );
    } );
} );

describe( 'Livewire pipeline', function (): void {
    it( 'requires the token', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.email', 'jane@example.com' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.verify' => 'required'] )
            ->assertSee( 'Please complete the verification check.' );
    } );

    it( 'verifies the token once after validation and never stores it', function (): void {
        fakeSiteVerify( ['success' => true] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.email', 'jane@example.com' )
            ->set( 'formData.verify', 'good-token' )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        Http::assertSentCount( 1 );

        $values = $this->form->submissions()->first()->values;

        expect( $values->pluck( 'field_name' )->all() )->toBe( ['email'] );
    } );

    it( 'does not spend the token while other fields are invalid', function (): void {
        Http::fake();

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.verify', 'good-token' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.email' => 'required'] );

        Http::assertNothingSent();
    } );

    it( 'rejects a failed token, resets the widget and saves nothing', function (): void {
        fakeSiteVerify( ['success' => false, 'error-codes' => ['invalid-input-response']] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.email', 'jane@example.com' )
            ->set( 'formData.verify', 'bad-token' )
            ->call( 'submit' )
            ->assertHasErrors( 'formData.verify' )
            ->assertSee( 'The verification check failed. Please try again.' )
            ->assertSet( 'formData.verify', '' )
            ->assertSet( 'isSubmitted', false )
            ->assertDispatched( 'forms-captcha-reset' );

        expect( $this->form->submissions()->count() )->toBe( 0 );
    } );

    it( 'moves a multi-step form back to the CAPTCHA\'s step when it fails', function (): void {
        $this->form->update( ['is_multi_step' => true] );
        $first  = FormStep::factory()->for( $this->form )->create( ['sort_order' => 0] );
        $second = FormStep::factory()->for( $this->form )->create( ['sort_order' => 1] );
        $this->captcha->update( ['step_id' => $first->id] );
        $this->email->update( ['step_id' => $second->id] );

        fakeSiteVerify( ['success' => false] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.email', 'jane@example.com' )
            ->set( 'formData.verify', 'token' )
            ->set( 'currentStepIndex', 1 )
            ->call( 'submit' )
            ->assertSet( 'currentStepIndex', 0 )
            ->assertHasErrors( 'formData.verify' );
    } );

    it( 'cannot be hidden by conditional logic, so a bot cannot skip it', function (): void {
        $this->captcha->update( ['conditional_logic' => ['action' => 'hide', 'logic' => 'all', 'rules' => [['field' => 'email', 'operator' => 'equals', 'value' => 'bot@example.com']]]] );

        expect( app( ConditionalLogicService::class )->getHiddenFields( $this->form->fields()->get(), ['email' => 'bot@example.com'] ) )->toMatchArray( ['verify' => false] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'bot@example.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.verify' );

        expect( file_get_contents( __DIR__ . '/../../../resources/js/shared/conditionalLogic.ts' ) )->toContain( "const isVisible = field.type === 'captcha' || evaluateFieldVisibility(" );
    } );

    it( 'skips a CAPTCHA the caller reports as hidden', function (): void {
        Http::fake();

        $errors = app( CaptchaVerifier::class )->verify( $this->form->fields, ['verify' => ''], ['verify' => true] );

        expect( $errors )->toBe( [] );
        Http::assertNothingSent();
    } );
} );

describe( 'API pipeline', function (): void {
    it( 'answers 422 with the field error when the token fails', function (): void {
        fakeSiteVerify( ['success' => false] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'jane@example.com', 'verify' => 'bad'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.verify' );

        expect( $this->form->submissions()->count() )->toBe( 0 );
    } );

    it( 'saves the submission without the token when it passes', function (): void {
        fakeSiteVerify( ['success' => true] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'jane@example.com', 'verify' => 'good'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        Http::assertSent( fn ( HttpRequest $request ): bool => 'good' === $request['response'] );

        expect( $this->form->submissions()->first()->values->pluck( 'field_name' )->all() )->toBe( ['email'] );
    } );

    it( 'requires the token before calling the provider', function (): void {
        Http::fake();

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['email' => 'jane@example.com'],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.verify' );

        Http::assertNothingSent();
    } );
} );

describe( 'rendering', function (): void {
    it( 'renders the widget container with the public settings', function (): void {
        $html = FieldRenderer::render( $this->captcha );

        expect( $html )->toContain( 'formsCaptcha(' )
            ->toContain( 'wire:ignore' )
            ->toContain( 'data-forms-captcha="turnstile"' )
            ->toContain( 'formData.verify' )
            ->toContain( 'ts-site' )
            ->not->toContain( 'ts-secret' );
    } );

    it( 'registers the formsCaptcha Alpine component with the renderer', function (): void {
        $view = file_get_contents( __DIR__ . '/../../../resources/views/livewire/form-renderer.blade.php' );

        expect( $view )->toContain( "Alpine.data('formsCaptcha'" )
            ->toContain( "document.addEventListener('submit', this.onSubmit, true);" )
            ->toContain( "window.addEventListener('forms-captcha-reset', this.onReset);" );
    } );

    it( 'sends the widget settings to the React and Vue renderers', function (): void {
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();
        $field  = collect( $fields )->firstWhere( 'name', 'verify' );

        expect( $field['captcha'] )->toMatchArray( ['provider' => 'turnstile', 'site_key' => 'ts-site'] )
            ->and( json_encode( $fields ) )->not->toContain( 'ts-secret' );
    } );
} );
