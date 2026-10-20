<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\ConditionalLogic;
use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\FieldTypes\RatingFieldType;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/**
 * Validates one rating value against a rating field's rules.
 */
function ratingPasses( FormField $field, mixed $value ): bool
{
    return Validator::make( ['rating' => $value], ['rating' => $field->buildValidationRules()] )->passes();
}

describe( 'rating field type registration', function (): void {
    it( 'is registered in the advanced category with a five-star default', function (): void {
        $config = FieldTypes::getTypeConfig( 'rating' );

        expect( FieldTypes::typeExists( 'rating' ) )->toBeTrue()
            ->and( FieldTypes::CATEGORY_FIELDS['advanced'] )->toContain( 'rating' )
            ->and( $config )->toMatchArray( [
                'label'                  => 'Rating',
                'icon'                   => 'o-star',
                'category'               => 'advanced',
                'supports_default_value' => true,
                'defaults'               => ['label' => 'Rating', 'field_config' => ['max_rating' => 5]],
            ] )
            ->and( array_column( $config['settings'], 'key' ) )->toBe( ['max_rating', 'allow_half', 'size', 'read_only'] );
    } );

    it( 'can be added from the Livewire builder palette', function (): void {
        $form = Form::factory()->create();

        Livewire::test( FormBuilder::class, ['form' => $form] )
            ->assertSee( 'Rating' )
            ->call( 'addField', 'rating' );

        $field = $form->fields()->first();

        expect( $field->type )->toBe( 'rating' )
            ->and( $field->field_config )->toBe( ['max_rating' => 5] );
    } );

    it( 'supports numeric comparison operators in conditional logic', function (): void {
        $operators = array_keys( ConditionalLogic::getOperatorsForType( 'rating' ) );

        expect( $operators )->toContain( 'equals', 'not_equals', 'greater_than', 'less_than', 'greater_or_equal', 'less_or_equal', 'is_empty', 'is_not_empty' )
            ->and( $operators )->not->toContain( 'contains', 'checked' );
    } );
} );

describe( 'rating validation', function (): void {
    it( 'accepts whole stars from 0 to the max', function (): void {
        $field = FormField::factory()->create( ['type' => 'rating', 'field_config' => ['max_rating' => 5]] );

        expect( $field->buildValidationRules() )->toBe( ['nullable', 'numeric', 'between:0,5', 'multiple_of:1'] )
            ->and( ratingPasses( $field, '0' ) )->toBeTrue()
            ->and( ratingPasses( $field, '5' ) )->toBeTrue()
            ->and( ratingPasses( $field, '' ) )->toBeTrue()
            ->and( ratingPasses( $field, '6' ) )->toBeFalse()
            ->and( ratingPasses( $field, '-1' ) )->toBeFalse()
            ->and( ratingPasses( $field, '2.5' ) )->toBeFalse()
            ->and( ratingPasses( $field, 'five' ) )->toBeFalse();
    } );

    it( 'accepts half stars when allowed', function (): void {
        $field = FormField::factory()->create( ['type' => 'rating', 'field_config' => ['max_rating' => 3, 'allow_half' => true]] );

        expect( $field->buildValidationRules() )->toContain( 'multiple_of:0.5', 'between:0,3' )
            ->and( ratingPasses( $field, '2.5' ) )->toBeTrue()
            ->and( ratingPasses( $field, '2.25' ) )->toBeFalse();
    } );

    it( 'requires a rating above 0 when required', function (): void {
        $field = FormField::factory()->required()->create( ['type' => 'rating'] );

        expect( $field->buildValidationRules() )->toBe( ['required', 'numeric', 'between:0,5', 'multiple_of:1', 'gt:0'] )
            ->and( ratingPasses( $field, '0' ) )->toBeFalse()
            ->and( ratingPasses( $field, '' ) )->toBeFalse()
            ->and( ratingPasses( $field, '1' ) )->toBeTrue();
    } );

    it( 'clamps the max rating to 1 through 10', function ( mixed $configured, int $expected ): void {
        $field = FormField::factory()->make( ['type' => 'rating', 'field_config' => ['max_rating' => $configured]] );

        expect( RatingFieldType::maxRating( $field ) )->toBe( $expected );
    } )->with( [
        'unset'    => [null, 5],
        'zero'     => [0, 5],
        'too many' => [50, 10],
        'string'   => ['7', 7],
    ] );

    it( 'has custom messages', function (): void {
        $field    = FormField::factory()->required()->create( ['type' => 'rating', 'name' => 'stars', 'label' => 'Service'] );
        $messages = app( SubmissionService::class )->buildValidationMessages( collect( [$field] ) );

        expect( $messages['formData.stars.gt'] )->toBe( 'Please choose a rating for Service.' )
            ->and( $messages['formData.stars.between'] )->toBe( 'The Service rating must be between 0 and 5.' )
            ->and( $messages['formData.stars.multiple_of'] )->toBe( 'The Service rating must be a whole star.' );
    } );
} );

describe( 'rating storage, display and export', function (): void {
    beforeEach( function (): void {
        $this->form  = Form::factory()->create();
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'rating',
            'name'         => 'stars',
            'label'        => 'Stars',
            'field_config' => ['max_rating' => 5, 'allow_half' => true],
        ] );
    } );

    it( 'stores a plain number and no rating as empty', function ( mixed $submitted, string $stored ): void {
        $submission = app( SubmissionService::class )->create( $this->form, ['stars' => $submitted] );

        expect( $submission->values()->first()->value )->toBe( $stored );
    } )->with( [
        'whole'      => ['4', '4'],
        'trailing 0' => ['4.0', '4'],
        'half'       => ['3.5', '3.5'],
        'integer'    => [2, '2'],
        'no rating'  => ['0', ''],
    ] );

    it( 'shows the rating against the max and exports the number', function (): void {
        $submission = FormSubmission::factory()->for( $this->form )->create();
        FormSubmissionValue::factory()->create( [
            'submission_id' => $submission->id,
            'field_id'      => $this->field->id,
            'field_name'    => 'stars',
            'field_label'   => 'Stars',
            'field_type'    => 'rating',
            'value'         => '4.5',
            'value_array'   => null,
        ] );

        $submission->load( 'values.field' );

        $row = app( ExportService::class )->buildRow( $this->form, $submission, $this->form->fields()->get() );

        expect( $submission->getValue( 'stars' ) )->toBe( '4.5 / 5' )
            ->and( $row[3] )->toBe( '4.5' );
    } );

    it( 'shows nothing for no rating and the bare number once the field is gone', function (): void {
        $unrated = FormSubmissionValue::factory()->make( ['field_id' => $this->field->id, 'field_type' => 'rating', 'value' => '', 'value_array' => null] );
        $orphan  = FormSubmissionValue::factory()->make( ['field_id' => null, 'field_type' => 'rating', 'value' => '3', 'value_array' => null] );

        expect( $unrated->display_value )->toBe( '' )
            ->and( $orphan->display_value )->toBe( '3' );
    } );

    it( 'compares ratings numerically in conditional logic', function (): void {
        $target = FormField::factory()->for( $this->form )->create( [
            'name'              => 'why',
            'conditional_logic' => [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [['field' => 'stars', 'operator' => 'less_than', 'value' => '3']],
            ],
        ] );

        $service = app( ConditionalLogicService::class );
        $fields  = $this->form->fields()->get();

        expect( $service->getHiddenFields( $fields, ['stars' => '2'] )[ $target->name ] ?? false )->toBeFalse()
            ->and( $service->getHiddenFields( $fields, ['stars' => '4.5'] )[ $target->name ] ?? false )->toBeTrue();
    } );
} );

describe( 'rating Livewire renderer', function (): void {
    it( 'renders an accessible star radiogroup', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->required()->create( [
            'type'         => 'rating',
            'name'         => 'stars',
            'label'        => 'How did we do?',
            'help_text'    => null,
            'field_config' => ['max_rating' => 4, 'allow_half' => true, 'size' => 'lg'],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSeeHtml( 'role="radiogroup"' )
            ->assertSeeHtml( 'aria-labelledby="label-stars"' )
            ->assertSeeHtml( 'aria-required="true"' )
            ->assertSeeHtml( 'rating-lg' )
            ->assertSeeHtml( 'rating-half' )
            ->assertSeeHtml( 'aria-label="No rating"' )
            ->assertSeeHtml( 'aria-label="0.5 stars"' )
            ->assertSeeHtml( 'aria-label="1 star"' )
            ->assertSeeHtml( 'aria-label="4 stars"' )
            ->assertDontSeeHtml( 'aria-label="4.5 stars"' )
            ->assertSeeHtml( 'focus-visible:outline' )
            ->assertDontSeeHtml( 'aria-readonly' );
    } );

    it( 'submits a rating', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->required()->create( ['type' => 'rating', 'name' => 'stars'] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.stars', '0' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.stars' => 'gt'] )
            ->set( 'formData.stars', '4' )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        expect( $form->submissions()->first()->getValue( 'stars' ) )->toBe( '4 / 5' );
    } );
} );
