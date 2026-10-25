<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Support\DateTimeFormat;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Guards issue #123: date display formats and dropdown / field input modes,
 * and 12 / 24-hour time with an hour, minute and AM/PM dropdown mode.
 */
beforeEach( function (): void {
    $this->travelTo( Carbon::parse( '2026-06-15 10:00:00' ) );

    $this->form = Form::factory()->create( ['is_active' => true] );
    $this->date = FormField::factory()->for( $this->form )->create( ['type' => 'date', 'name' => 'born', 'label' => 'Birthdate', 'help_text' => null] );
    $this->time = FormField::factory()->for( $this->form )->create( ['type' => 'time', 'name' => 'at', 'label' => 'Time', 'help_text' => null] );
} );

describe( 'DateTimeFormat', function (): void {
    it( 'formats stored dates', function ( string $format, string $expected ): void {
        expect( DateTimeFormat::formatDate( '2026-03-05', $format ) )->toBe( $expected );
    } )->with( [
        'mdy' => ['mdy', '03/05/2026'],
        'dmy' => ['dmy', '05/03/2026'],
        'ymd' => ['ymd', '2026-03-05'],
    ] );

    it( 'leaves values that are not dates alone', function (): void {
        expect( DateTimeFormat::formatDate( 'soon', 'mdy' ) )->toBe( 'soon' )
            ->and( DateTimeFormat::formatDate( '2026-03-05', 'bogus' ) )->toBe( '2026-03-05' );
    } );

    it( 'formats stored times on a 12-hour clock', function ( string $time, string $expected ): void {
        expect( DateTimeFormat::formatTime( $time, '12' ) )->toBe( $expected );
    } )->with( [
        'midnight'     => ['00:05', '12:05 AM'],
        'morning'      => ['09:30', '9:30 AM'],
        'noon'         => ['12:00', '12:00 PM'],
        'afternoon'    => ['14:45', '2:45 PM'],
        'with seconds' => ['23:59:30', '11:59:30 PM'],
    ] );

    it( 'keeps 24-hour times and non-times as they are', function (): void {
        expect( DateTimeFormat::formatTime( '14:45', '24' ) )->toBe( '14:45' )
            ->and( DateTimeFormat::formatTime( 'later', '12' ) )->toBe( 'later' );
    } );

    it( 'offers minutes at the step interval', function (): void {
        expect( DateTimeFormat::minuteOptions( 900 ) )->toBe( [0, 15, 30, 45] )
            ->and( DateTimeFormat::minuteOptions( 3600 ) )->toBe( [0] )
            ->and( DateTimeFormat::minuteOptions( 30 ) )->toHaveCount( 60 )
            ->and( DateTimeFormat::minuteOptions( null ) )->toHaveCount( 60 );
    } );

    it( 'offers years within the bounds, latest first', function (): void {
        expect( DateTimeFormat::yearOptions( '2020-01-01', '2023-12-31' ) )->toBe( [2023, 2022, 2021, 2020] )
            ->and( DateTimeFormat::yearOptions( null, null ) )->toHaveCount( 111 )
            ->and( DateTimeFormat::yearOptions( null, null )[0] )->toBe( 2036 );
    } );
} );

describe( 'settings', function (): void {
    it( 'offers the input mode and format settings', function (): void {
        expect( array_column( FieldSettings::forTab( $this->date, 'general' ), 'key' ) )->toContain( 'date_input', 'date_format' )
            ->and( array_column( FieldSettings::forTab( $this->time, 'general' ), 'key' ) )->toContain( 'time_input', 'time_format' );
    } );

    it( 'defaults to the native pickers, ISO dates and a 24-hour clock', function (): void {
        expect( $this->date->getDateInputMode() )->toBe( 'picker' )
            ->and( $this->date->getDateFormat() )->toBe( 'ymd' )
            ->and( $this->time->getTimeInputMode() )->toBe( 'picker' )
            ->and( $this->time->getTimeFormat() )->toBe( '24' );
    } );
} );

describe( 'display', function (): void {
    it( 'shows stored dates and times in the field\'s format', function (): void {
        $this->date->update( ['field_config' => ['date_format' => 'dmy']] );
        $this->time->update( ['field_config' => ['time_format' => '12']] );

        $submission = FormSubmission::factory()->for( $this->form )->create();
        $date       = FormSubmissionValue::factory()->create( ['submission_id' => $submission->id, 'field_id' => $this->date->id, 'field_type' => 'date', 'value' => '1990-04-09'] );
        $time       = FormSubmissionValue::factory()->create( ['submission_id' => $submission->id, 'field_id' => $this->time->id, 'field_type' => 'time', 'value' => '18:30'] );

        expect( $date->fresh()->display_value )->toBe( '09/04/1990' )
            ->and( $time->fresh()->display_value )->toBe( '6:30 PM' )
            ->and( $date->fresh()->value )->toBe( '1990-04-09' );
    } );
} );

describe( 'date dropdowns and fields', function (): void {
    it( 'groups month, day and year dropdowns in a fieldset, in the format\'s order', function (): void {
        $this->date->update( ['field_config' => ['date_input' => 'dropdowns', 'date_format' => 'dmy', 'autocomplete' => 'bday']] );
        $html = FieldRenderer::render( $this->date->fresh(), '' );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( 'Birthdate' )
            ->toContain( '<legend' )
            ->toContain( 'for="field-born-day"' )
            ->toContain( 'for="field-born-month"' )
            ->toContain( 'autocomplete="bday-year"' )
            ->toContain( '<option value="12">December</option>' )
            ->toContain( "entangle('formData.born')" )
            ->not->toContain( 'type="date"' )
            ->and( strpos( $html, 'field-born-day' ) )->toBeLessThan( strpos( $html, 'field-born-month' ) )
            ->and( strpos( $html, 'field-born-month' ) )->toBeLessThan( strpos( $html, 'field-born-year' ) );
    } );

    it( 'limits the year dropdown to the field\'s age limits', function (): void {
        $this->date->update( ['field_config' => ['date_input' => 'dropdowns'], 'validation_rules' => ['min_age' => 18, 'max_age' => 20]] );
        $html = FieldRenderer::render( $this->date->fresh(), '' );

        expect( $html )->toContain( '<option value="2008">2008</option>' )
            ->toContain( '<option value="2005">2005</option>' )
            ->not->toContain( '<option value="2009">' )
            ->not->toContain( '<option value="2004">' );
    } );

    it( 'renders number fields with placeholders in the fields mode', function (): void {
        $this->date->update( ['field_config' => ['date_input' => 'fields', 'date_format' => 'mdy']] );

        expect( FieldRenderer::render( $this->date->fresh(), '' ) )->toContain( 'inputmode="numeric"' )
            ->toContain( 'placeholder="MM"' )
            ->toContain( 'maxlength="4"' )
            ->toContain( 'padded: true' )
            ->toContain( ': `${year}-${month' )
            ->not->toContain( "year.padStart(4, '0')" );
    } );

    it( 'stores the combined date as YYYY-MM-DD and rejects an impossible one', function (): void {
        $this->date->update( ['field_config' => ['date_input' => 'dropdowns']] );

        Livewire::test( FormRenderer::class, ['form' => $this->form->fresh()] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.born', '2026-02-30' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.born' => 'date'] )
            ->set( 'formData.born', '2026--05' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.born' => 'date'] )
            ->set( 'formData.born', '90-04-09' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.born' => 'date_format'] )
            ->set( 'formData.born', '2026-02-28' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( $this->form->submissions()->first()->values->firstWhere( 'field_name', 'born' )->value )->toBe( '2026-02-28' );
    } );
} );

/**
 * Returns the markup of one select, from its ID to its closing tag.
 */
function selectMarkup( string $html, string $id ): string
{
    $from = strpos( $html, 'id="' . $id . '"' );

    expect( $from )->not->toBeFalse();

    return substr( $html, $from, strpos( $html, '</select>', $from ) - $from );
}

describe( 'time dropdowns', function (): void {
    it( 'renders hour and minute dropdowns on a 24-hour clock', function (): void {
        $this->time->update( ['field_config' => ['time_input' => 'dropdowns', 'step' => 900]] );
        $html = FieldRenderer::render( $this->time->fresh(), '' );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( 'for="field-at-hour"' )
            ->toContain( 'for="field-at-minute"' )
            ->not->toContain( 'field-at-period' )
            ->and( selectMarkup( $html, 'field-at-hour' ) )->toContain( '<option value="0">00</option>' )->toContain( '<option value="23">23</option>' )
            ->and( selectMarkup( $html, 'field-at-minute' ) )->toContain( '<option value="45">45</option>' )->not->toContain( '<option value="10">' );
    } );

    it( 'adds an AM/PM dropdown on a 12-hour clock', function (): void {
        $this->time->update( ['field_config' => ['time_input' => 'dropdowns', 'time_format' => '12']] );
        $html = FieldRenderer::render( $this->time->fresh(), '' );

        expect( $html )->toContain( 'for="field-at-period"' )
            ->toContain( '<option value="pm">PM</option>' )
            ->and( selectMarkup( $html, 'field-at-hour' ) )->toContain( '<option value="12">12</option>' )->not->toContain( '<option value="0">' )->not->toContain( '<option value="13">' );
    } );

    it( 'stores the combined time as HH:MM', function (): void {
        $this->time->update( ['field_config' => ['time_input' => 'dropdowns', 'time_format' => '12']] );

        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['at' => '18:30'],
            '_form_loaded_at' => time() - 10,
        ] )->assertCreated();

        expect( $this->form->submissions()->first()->values->firstWhere( 'field_name', 'at' )->value )->toBe( '18:30' );
    } );
} );
