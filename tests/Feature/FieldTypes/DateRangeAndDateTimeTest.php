<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\DateTimeFieldType;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\PopulationContext;
use ArtisanPackUI\Forms\Support\SubmissionRules;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Guards issue #128: the date range and date and time fields.
 */
beforeEach( function (): void {
    Carbon::setTestNow( '2026-10-25 12:00:00' );

    $this->form = Form::factory()->create( ['is_active' => true] );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

describe( 'date range', function (): void {
    beforeEach( function (): void {
        $this->stay = FormField::factory()->for( $this->form )->create( [
            'type'        => 'date_range',
            'name'        => 'stay',
            'label'       => 'Stay',
            'is_required' => true,
        ] );
    } );

    it( 'is a composite of a start and an end date', function (): void {
        expect( FieldTypes::getTypeConfig( 'date_range' )['category'] )->toBe( 'advanced' )
            ->and( array_keys( $this->stay->getEnabledSubInputs() ) )->toBe( ['start', 'end'] )
            ->and( array_column( $this->stay->getEnabledSubInputs(), 'type' ) )->toBe( ['date', 'date'] )
            ->and( $this->stay->getRequiredSubInputKeys() )->toBe( ['start', 'end'] );
    } );

    it( 'checks both dates and the order of the range', function (): void {
        $rules = $this->stay->buildKeyedValidationRules();

        expect( $rules[''] )->toBe( ['nullable', 'array', SubmissionRules::DATE_RANGE] )
            ->and( $rules['start'] )->toBe( ['required', 'date', 'date_format:Y-m-d'] )
            ->and( $rules['end'] )->toBe( ['required', 'date', 'date_format:Y-m-d'] );
    } );

    it( 'applies the min and max dates to both dates', function (): void {
        $this->stay->update( ['validation_rules' => ['min_date' => 'today', 'max_date' => '+1 year']] );
        $rules = $this->stay->fresh()->buildKeyedValidationRules();

        expect( $rules['start'] )->toContain( 'after_or_equal:2026-10-25', 'before_or_equal:2027-10-25' )
            ->and( $rules['end'] )->toContain( 'after_or_equal:2026-10-25', 'before_or_equal:2027-10-25' );
    } );

    it( 'passes a complete, ordered range or an empty one', function ( mixed $value, bool $passes ): void {
        expect( SubmissionRules::isDateRange( $value ) )->toBe( $passes );
    } )->with( [
        'ordered'        => [['start' => '2026-11-01', 'end' => '2026-11-05'], true],
        'same day'       => [['start' => '2026-11-01', 'end' => '2026-11-01'], true],
        'reversed'       => [['start' => '2026-11-05', 'end' => '2026-11-01'], false],
        'start only'     => [['start' => '2026-11-05', 'end' => ''], false],
        'end only'       => [['start' => '', 'end' => '2026-11-05'], false],
        'empty'          => [['start' => '', 'end' => ''], true],
        'invalid format' => [['start' => 'soon', 'end' => '2026-11-05'], true],
        'not an array'   => ['2026-11-05', true],
    ] );

    it( 'rejects a reversed range in Livewire and the API', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.stay', ['start' => '2026-11-05', 'end' => '2026-11-01'] )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.stay' => SubmissionRules::DATE_RANGE] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['stay' => ['start' => '2026-11-05', 'end' => '2026-11-01']],
            '_form_loaded_at' => now()->subSeconds( 10 )->getTimestamp(),
        ] )->assertUnprocessable()
            ->assertJsonValidationErrors( ['data.stay' => 'Please enter both dates for Stay, with the end date on or after the start date.'] );
    } );

    it( 'stores the range and shows it in the field\'s date format', function (): void {
        $this->stay->update( ['field_config' => ['date_format' => 'mdy']] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['stay' => ['start' => '2026-11-01', 'end' => '2026-11-05']],
            '_form_loaded_at' => now()->subSeconds( 10 )->getTimestamp(),
        ] )->assertCreated();

        $value = $this->form->submissions()->first()->values->first();

        expect( $value->value_array )->toBe( ['start' => '2026-11-01', 'end' => '2026-11-05'] )
            ->and( $value->display_value )->toBe( '11/01/2026 – 11/05/2026' );
    } );

    it( 'renders two labelled date inputs in a fieldset', function (): void {
        $html = FieldRenderer::render( $this->stay, ['start' => '', 'end' => ''] );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( 'type="date"' )
            ->toContain( 'id="field-stay-start"' )
            ->toContain( 'id="field-stay-end"' )
            ->toContain( 'Start Date' )
            ->toContain( 'End Date' );
    } );

    it( 'bounds both date inputs by the limits', function (): void {
        $this->stay->update( ['validation_rules' => ['min_date' => '2026-11-01', 'max_date' => '2026-11-30']] );

        $html = FieldRenderer::render( $this->stay->fresh(), ['start' => '', 'end' => ''] );

        expect( substr_count( $html, 'min="2026-11-01"' ) )->toBe( 2 )
            ->and( substr_count( $html, 'max="2026-11-30"' ) )->toBe( 2 );
    } );
} );

describe( 'date and time', function (): void {
    beforeEach( function (): void {
        $this->appointment = FormField::factory()->for( $this->form )->create( [
            'type'  => 'datetime',
            'name'  => 'appointment',
            'label' => 'Appointment',
        ] );
    } );

    it( 'converts datetime-local values for storage and back', function (): void {
        expect( DateTimeFieldType::normalizeValue( '2026-11-01T09:30' ) )->toBe( '2026-11-01 09:30' )
            ->and( DateTimeFieldType::normalizeValue( '2026-11-01T09:30:15' ) )->toBe( '2026-11-01 09:30' )
            ->and( DateTimeFieldType::normalizeValue( '2026-11-01 09:30' ) )->toBe( '2026-11-01 09:30' )
            ->and( DateTimeFieldType::normalizeValue( 'tomorrow' ) )->toBe( 'tomorrow' )
            ->and( DateTimeFieldType::toInputValue( '2026-11-01 09:30' ) )->toBe( '2026-11-01T09:30' )
            ->and( DateTimeFieldType::toInputValue( 'nope' ) )->toBe( '' );
    } );

    it( 'checks the format and the min and max days', function (): void {
        $this->appointment->update( ['validation_rules' => ['min_date' => 'today', 'max_date' => '+30 days']] );

        expect( $this->appointment->fresh()->buildValidationRules() )->toBe( [
            'nullable',
            'date_format:Y-m-d H:i',
            'after_or_equal:2026-10-25 00:00',
            'before_or_equal:2026-11-24 23:59',
        ] );
    } );

    it( 'accepts a datetime-local value and stores it as Y-m-d H:i', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->afterSpamDelay()
            ->set( 'formData.appointment', '2026-11-01T09:30' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->first()->value )->toBe( '2026-11-01 09:30' );
    } );

    it( 'rejects other formats and days outside the limits through the API', function (): void {
        $this->appointment->update( ['validation_rules' => ['min_date' => 'today']] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['appointment' => 'next tuesday'],
            '_form_loaded_at' => now()->subSeconds( 10 )->getTimestamp(),
        ] )->assertUnprocessable()->assertJsonValidationErrors( ['data.appointment' => 'Please enter a valid date and time for Appointment.'] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['appointment' => '2026-10-24T23:00'],
            '_form_loaded_at' => now()->subSeconds( 10 )->getTimestamp(),
        ] )->assertUnprocessable()->assertJsonValidationErrors( ['data.appointment' => 'The Appointment must be on or after 2026-10-25.'] );
    } );

    it( 'shows the value in the field\'s date and time formats', function (): void {
        $this->appointment->update( ['field_config' => ['date_format' => 'dmy', 'time_format' => '12']] );

        $value = FormSubmissionValue::factory()->create( [
            'submission_id' => FormSubmission::factory()->for( $this->form )->create()->id,
            'field_id'      => $this->appointment->id,
            'field_name'    => 'appointment',
            'field_label'   => 'Appointment',
            'field_type'    => 'datetime',
            'value'         => '2026-11-01 14:30',
        ] );

        expect( $value->fresh()->display_value )->toBe( '01/11/2026 2:30 PM' );
    } );

    it( 'starts with its default as a datetime-local value', function (): void {
        $this->appointment->update( ['default_value' => '2026-11-01 09:30'] );

        expect( DynamicPopulation::initialValue( $this->appointment->fresh(), PopulationContext::trusted() ) )->toBe( '2026-11-01T09:30' );
    } );

    it( 'renders a datetime-local input bounded by the limits', function (): void {
        $this->appointment->update( ['validation_rules' => ['min_date' => '2026-11-01', 'max_date' => '2026-11-30'], 'field_config' => ['step' => 900]] );

        expect( FieldRenderer::render( $this->appointment->fresh() ) )->toContain( 'type="datetime-local"' )
            ->toContain( 'min="2026-11-01T00:00"' )
            ->toContain( 'max="2026-11-30T23:59"' )
            ->toContain( 'step="900"' )
            ->toContain( 'wire:model.live="formData.appointment"' );
    } );
} );
