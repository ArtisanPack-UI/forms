<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\PredefinedChoices;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use Tests\Models\User as TestUser;

/**
 * Guards issue #104: bulk-edited choices and predefined choice lists.
 */
describe( 'predefined choice lists', function (): void {
    afterEach( function (): void {
        removeAllFilters( 'ap.forms.predefinedChoices' );
    } );

    it( 'ship the built-in lists with labels and choices', function (): void {
        $lists = PredefinedChoices::all();

        expect( array_keys( $lists ) )->toBe( ['countries', 'us_states', 'canadian_provinces', 'months', 'days_of_week', 'age_ranges', 'satisfaction', 'agreement', 'importance'] )
            ->and( $lists['countries']['label'] )->toBe( 'Countries' )
            ->and( $lists['countries']['choices'] )->toHaveCount( 249 )
            ->toContain( ['value' => 'US', 'label' => 'United States'] )
            ->and( $lists['us_states']['choices'] )->toHaveCount( 51 )
            ->and( $lists['canadian_provinces']['choices'] )->toHaveCount( 13 )
            ->and( $lists['months']['choices'][0] )->toBe( ['value' => 'January', 'label' => 'January'] )
            ->and( $lists['days_of_week']['choices'] )->toHaveCount( 7 )
            ->and( array_column( $lists['satisfaction']['choices'], 'value' ) )->toBe( ['very-satisfied', 'satisfied', 'neutral', 'dissatisfied', 'very-dissatisfied'] );
    } );

    it( 'translate labels but keep values', function (): void {
        app( 'translator' )->addLines( ['*.January' => 'Janvier'], 'fr' );
        app()->setLocale( 'fr' );

        expect( PredefinedChoices::get( 'months' )[0] )->toBe( ['value' => 'January', 'label' => 'Janvier'] );
    } );

    it( 'let apps add, change and remove lists through the ap.forms.predefinedChoices filter', function (): void {
        addFilter( 'ap.forms.predefinedChoices', function ( array $lists ): array {
            $lists['sizes']  = ['label' => 'Sizes', 'choices' => ['S' => 'Small', 'M' => 'Medium']];
            $lists['colors'] = ['label' => 'Colors', 'choices' => ['Red', 'Blue']];
            $lists['broken'] = ['label' => '', 'choices' => ['x']];
            unset( $lists['importance'] );

            return $lists;
        } );

        $lists = PredefinedChoices::all();

        expect( $lists['sizes']['choices'] )->toBe( [['value' => 'S', 'label' => 'Small'], ['value' => 'M', 'label' => 'Medium']] )
            ->and( $lists['colors']['choices'] )->toBe( [['value' => 'Red', 'label' => 'Red'], ['value' => 'Blue', 'label' => 'Blue']] )
            ->and( $lists )->not->toHaveKey( 'broken' )
            ->not->toHaveKey( 'importance' );
    } );

    it( 'return null for an unknown list', function (): void {
        expect( PredefinedChoices::get( 'nope' ) )->toBeNull();
    } );
} );

describe( 'bulk choice parsing', function (): void {
    it( 'reads one choice per line, as value|label or a bare label', function (): void {
        expect( PredefinedChoices::parseBulk( "red|Red\r\nGreen\n\n  blue | Blue sky  \n|Purple\nyellow|\nx|a|b" ) )->toBe( [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'Green', 'label' => 'Green'],
            ['value' => 'blue', 'label' => 'Blue sky'],
            ['value' => 'Purple', 'label' => 'Purple'],
            ['value' => 'yellow', 'label' => 'yellow'],
            ['value' => 'x', 'label' => 'a|b'],
        ] );
    } );

    it( 'keeps the first line of a repeated value and skips empty lines', function (): void {
        expect( PredefinedChoices::parseBulk( "a|First\na|Second\n|\n   " ) )->toBe( [['value' => 'a', 'label' => 'First']] );
    } );

    it( 'stops at the maximum number of choices', function (): void {
        $text = implode( "\n", range( 1, PredefinedChoices::MAX_CHOICES + 5 ) );

        expect( PredefinedChoices::parseBulk( $text ) )->toHaveCount( PredefinedChoices::MAX_CHOICES );
    } );

    it( 'writes choices back in the same syntax', function (): void {
        $choices = [['value' => 'a', 'label' => 'a'], ['value' => 'b', 'label' => 'Bee']];

        expect( PredefinedChoices::toBulk( $choices ) )->toBe( "a\nb|Bee" )
            ->and( PredefinedChoices::parseBulk( PredefinedChoices::toBulk( $choices ) ) )->toBe( $choices );
    } );

    it( 'replaces options, keeping the default mark of values that stay', function (): void {
        $existing = [['value' => 'a', 'label' => 'A', 'default' => true], ['value' => 'b', 'label' => 'B', 'default' => true]];

        expect( PredefinedChoices::apply( $existing, [['value' => 'a', 'label' => 'New A'], ['value' => 'c', 'label' => 'C']] ) )->toBe( [
            ['value' => 'a', 'label' => 'New A', 'default' => true],
            ['value' => 'c', 'label' => 'C'],
        ] );
    } );

    it( 'appends new values after the existing options', function (): void {
        $existing = [['value' => 'a', 'label' => 'A', 'default' => true]];

        expect( PredefinedChoices::apply( $existing, [['value' => 'a', 'label' => 'Again'], ['value' => 'b', 'label' => 'B']], 'append' ) )->toBe( [
            ['value' => 'a', 'label' => 'A', 'default' => true],
            ['value' => 'b', 'label' => 'B'],
        ] );
    } );
} );

describe( 'bulk editing in the Livewire builder', function (): void {
    beforeEach( function (): void {
        $this->form  = Form::factory()->create();
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'         => 'checkbox_group',
            'name'         => 'colors',
            'field_config' => ['options' => [['value' => 'red', 'label' => 'Red', 'default' => true]], 'layout' => '2-col'],
        ] );
    } );

    it( 'replaces the selected field\'s options and keeps its other settings', function (): void {
        Livewire::actingAs( TestUser::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->call( 'applyBulkChoices', "red|Crimson\nblue|Blue" );

        expect( $this->field->fresh()->field_config )->toBe( [
            'options' => [['value' => 'red', 'label' => 'Crimson', 'default' => true], ['value' => 'blue', 'label' => 'Blue']],
            'layout'  => '2-col',
        ] );
    } );

    it( 'appends a predefined list', function (): void {
        Livewire::actingAs( TestUser::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->call( 'applyBulkChoices', PredefinedChoices::toBulk( PredefinedChoices::get( 'days_of_week' ) ), 'append' );

        expect( array_column( $this->field->fresh()->options, 'value' ) )->toBe( ['red', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] );
    } );

    it( 'ignores fields without options', function (): void {
        $text = FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'note', 'sort_order' => 2] );

        Livewire::actingAs( TestUser::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $text->uuid )
            ->call( 'applyBulkChoices', 'a' );

        expect( $text->fresh()->field_config )->toBeNull();
    } );

    it( 'offers the bulk editor and predefined lists in the options tab', function (): void {
        Livewire::actingAs( TestUser::factory()->create() )->test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->field->uuid )
            ->assertSee( 'Bulk Add / Predefined Choices' )
            ->assertSee( 'Replace Options' )
            ->assertSee( 'Add to Options' )
            ->assertSee( 'Check the options that start selected.' )
            ->assertSeeHtml( 'Canadian Provinces and Territories' );
    } );
} );

describe( 'predefined choices API', function (): void {
    beforeEach( function (): void {
        $this->user = ( new class extends User {
            protected $table = 'users';

            protected $fillable = ['name', 'email', 'password'];
        } )->create( ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt( 'secret' )] );
    } );

    it( 'lists the predefined lists for the React and Vue builders', function (): void {
        $form = Form::factory()->create();

        $this->actingAs( $this->user )
            ->getJson( route( 'api.forms.fields.predefined-choices', $form ) )
            ->assertSuccessful()
            ->assertJsonPath( 'data.0.key', 'countries' )
            ->assertJsonPath( 'data.0.label', 'Countries' )
            ->assertJsonPath( 'data.3.choices.0', ['value' => 'January', 'label' => 'January'] );
    } );

    it( 'requires authentication', function (): void {
        $this->getJson( route( 'api.forms.fields.predefined-choices', Form::factory()->create() ) )->assertUnauthorized();
    } );
} );
