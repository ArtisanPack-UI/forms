<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\DateConstraints;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Guards issue #122: relative min/max dates, "no past / future dates",
 * disabled days and minimum / maximum age on date fields.
 */
beforeEach( function (): void {
    // Monday 15 June 2026
    $this->travelTo( Carbon::parse( '2026-06-15 10:00:00' ) );

    $this->form = Form::factory()->create( ['is_active' => true] );
    $this->date = FormField::factory()->for( $this->form )->create( [
        'type'  => 'date',
        'name'  => 'when',
        'label' => 'When',
    ] );
} );

describe( 'DateConstraints', function (): void {
    it( 'parses min and max dates typed in the builder', function ( string $raw, ?string $expected ): void {
        expect( DateConstraints::parseExpression( $raw ) )->toBe( $expected );
    } )->with( [
        'absolute'          => ['2026-12-31', '2026-12-31'],
        'impossible'        => ['2026-02-30', null],
        'today'             => [' Today ', 'today'],
        'days'              => ['+30 days', '+30 days'],
        'singular'          => ['+1 days', '+1 day'],
        'no space'          => ['-18years', '-18 years'],
        'weeks'             => ['+2 WEEK', '+2 weeks'],
        'zero is today'     => ['-0 months', 'today'],
        'unsigned'          => ['30 days', null],
        'unknown unit'      => ['+3 fortnights', null],
        'free text'         => ['next tuesday', null],
    ] );

    it( 'resolves relative dates from today without overflowing months', function ( string $expression, string $today, string $expected ): void {
        expect( DateConstraints::resolve( $expression, Carbon::parse( $today ) ) )->toBe( $expected );
    } )->with( [
        'today'          => ['today', '2026-06-15', '2026-06-15'],
        'days'           => ['+30 days', '2026-06-15', '2026-07-15'],
        'weeks back'     => ['-2 weeks', '2026-06-15', '2026-06-01'],
        'month end'      => ['+1 month', '2026-01-31', '2026-02-28'],
        'leap year back' => ['-1 year', '2024-02-29', '2023-02-28'],
        'absolute'       => ['2027-01-01', '2026-06-15', '2027-01-01'],
    ] );

    it( 'resolves relative dates from the current day by default', function (): void {
        expect( DateConstraints::resolve( '+1 day' ) )->toBe( '2026-06-16' )
            ->and( DateConstraints::resolve( 'garbage' ) )->toBeNull()
            ->and( DateConstraints::resolve( null ) )->toBeNull();
    } );

    it( 'works out birthdate bounds for ages', function (): void {
        expect( DateConstraints::latestBirthdate( 18 ) )->toBe( '2008-06-15' )
            ->and( DateConstraints::earliestBirthdate( 65 ) )->toBe( '1960-06-16' );
    } );

    it( 'checks disabled days', function (): void {
        expect( DateConstraints::isAllowedDay( '2026-06-13', ['weekends'] ) )->toBeFalse()
            ->and( DateConstraints::isAllowedDay( '2026-06-15', ['weekends'] ) )->toBeTrue()
            ->and( DateConstraints::isAllowedDay( '2026-12-25', ['2026-12-25'] ) )->toBeFalse()
            ->and( DateConstraints::isAllowedDay( 'not a date', ['weekends'] ) )->toBeTrue();
    } );

    it( 'normalizes disabled date lists', function (): void {
        expect( DateConstraints::normalizeDates( ['2027-01-01', ' 2026-12-25', 'nope', '2026-02-30', '2026-12-25'] ) )->toBe( ['2026-12-25', '2027-01-01'] );
    } );
} );

describe( 'settings', function (): void {
    it( 'offers the constraint settings in the validation tab', function (): void {
        expect( array_column( FieldSettings::forTab( $this->date, 'validation' ), 'key' ) )
            ->toContain( 'min_date', 'max_date', 'date_range', 'disable_weekends', 'disabled_dates', 'min_age', 'max_age' );
    } );

    it( 'stores relative min and max dates', function ( string $raw, ?string $stored ): void {
        $update = FieldSettings::update( $this->date, FieldSettings::find( $this->date, 'min_date' ), $raw );

        expect( $update['validation_rules']['min_date'] ?? null )->toBe( $stored );
    } )->with( [
        'relative' => ['+7 Days', '+7 days'],
        'absolute' => ['2026-01-01', '2026-01-01'],
        'invalid'  => ['soon', null],
    ] );
} );

describe( 'validation rules', function (): void {
    it( 'keeps absolute limits as they are', function (): void {
        $this->date->update( ['validation_rules' => ['min_date' => '2026-01-01', 'max_date' => '2026-12-31']] );

        expect( $this->date->fresh()->buildValidationRules() )->toBe( ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:2026-01-01', 'before_or_equal:2026-12-31'] );
    } );

    it( 'resolves relative limits for today', function (): void {
        $this->date->update( ['validation_rules' => ['min_date' => 'today', 'max_date' => '+30 days']] );

        expect( $this->date->fresh()->buildValidationRules() )->toBe( ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:2026-06-15', 'before_or_equal:2026-07-15'] );
    } );

    it( 'narrows the limits with no past or no future dates', function (): void {
        $this->date->update( ['validation_rules' => ['date_range' => 'no_past', 'min_date' => '2026-01-01']] );
        expect( $this->date->fresh()->getDateLimits() )->toBe( ['min' => '2026-06-15', 'max' => null] );

        $this->date->update( ['validation_rules' => ['date_range' => 'no_future', 'max_date' => '2026-06-01']] );
        expect( $this->date->fresh()->getDateLimits() )->toBe( ['min' => null, 'max' => '2026-06-01'] );
    } );

    it( 'adds the disabled days and age rules', function (): void {
        $this->date->update( ['validation_rules' => [
            'disable_weekends' => true,
            'disabled_dates'   => ['2026-12-25'],
            'min_age'          => 18,
            'max_age'          => 120,
        ]] );
        $field = $this->date->fresh();

        expect( $field->buildValidationRules() )->toBe( [
            'nullable',
            'date',
            'date_format:Y-m-d',
            SubmissionRules::DISABLED_DATES . ':weekends,2026-12-25',
            SubmissionRules::MIN_AGE . ':18',
            SubmissionRules::MAX_AGE . ':120',
        ] )
            ->and( $field->buildValidationMessages()[ SubmissionRules::MIN_AGE ] )->toBe( 'You must be at least 18 years old for When.' )
            ->and( $field->getDateBounds() )->toBe( ['min' => '1905-06-16', 'max' => '2008-06-15'] );
    } );
} );

describe( 'submission', function (): void {
    it( 'rejects past dates with no past dates', function (): void {
        $this->date->update( ['validation_rules' => ['date_range' => 'no_past']] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.when', '2026-06-14' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.when' => 'after_or_equal'] )
            ->set( 'formData.when', '2026-06-15' )
            ->call( 'submit' )
            ->assertHasNoErrors();
    } );

    it( 'rejects weekends and disabled dates', function ( string $date, bool $passes ): void {
        $this->date->update( ['validation_rules' => ['disable_weekends' => true, 'disabled_dates' => ['2026-06-17']]] );

        $response = $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['when' => $date],
            '_form_loaded_at' => time() - 10,
        ] );

        $passes ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors( 'data.when' );
    } )->with( [
        'saturday'      => ['2026-06-13', false],
        'disabled date' => ['2026-06-17', false],
        'weekday'       => ['2026-06-16', true],
    ] );

    it( 'checks a birthdate against the minimum and maximum age', function ( string $birthdate, ?string $failedRule ): void {
        $this->date->update( ['validation_rules' => ['min_age' => 18, 'max_age' => 65]] );

        $test = Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.when', $birthdate )
            ->call( 'submit' );

        null === $failedRule ? $test->assertHasNoErrors() : $test->assertHasErrors( ['formData.when' => $failedRule] );
    } )->with( [
        'eighteen today'     => ['2008-06-15', null],
        'eighteen tomorrow'  => ['2008-06-16', SubmissionRules::MIN_AGE],
        'sixty-five'         => ['1960-06-16', null],
        'sixty-six today'    => ['1960-06-15', SubmissionRules::MAX_AGE],
    ] );
} );

it( 'rejects a birthdate written another way, so it cannot skip the age check', function ( string $birthdate ): void {
    $this->date->update( ['validation_rules' => ['min_age' => 18]] );

    $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['when' => $birthdate],
        '_form_loaded_at' => time() - 10,
    ] )->assertUnprocessable()->assertJsonValidationErrors( ['data.when' => 'Please enter a valid date for When.'] );
} )->with( [
    'words'     => ['June 15 2015'],
    'with time' => ['2015-06-15T00:00'],
    'slashes'   => ['06/15/2015'],
] );

it( 'requires YYYY-MM-DD dates even without disabled days or ages', function (): void {
    expect( $this->date->buildValidationRules() )->toBe( ['nullable', 'date', 'date_format:Y-m-d'] );

    $this->postJson( route( 'api.forms.submit', $this->form ), [
        'data'            => ['when' => 'June 15 2015'],
        '_form_loaded_at' => time() - 10,
    ] )->assertUnprocessable()->assertJsonValidationErrors( ['data.when' => 'Please enter a valid date for When.'] );
} );

describe( 'rendering', function (): void {
    it( 'sets the native min and max from the resolved limits and ages', function (): void {
        $this->date->update( ['validation_rules' => ['min_date' => '-1 year', 'max_date' => 'today', 'min_age' => 1]] );

        expect( FieldRenderer::render( $this->date->fresh(), '' ) )->toContain( 'min="2025-06-15"' )->toContain( 'max="2025-06-15"' );
    } );
} );
