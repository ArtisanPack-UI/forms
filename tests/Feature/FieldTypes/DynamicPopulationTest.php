<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Http\Resources\FormFieldResource;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\MergeTags;
use ArtisanPackUI\Forms\Support\PopulationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Models\User;

/**
 * Guards issue #95: dynamic population of field values.
 */

/**
 * Makes a field with dynamic population on.
 *
 * @param  array<string, mixed>  $attributes  Field attributes.
 * @param  array<string, mixed>  $config  Extra field_config entries.
 */
function populatedField( array $attributes = [], array $config = [] ): FormField
{
    return FormField::factory()->make( array_merge( [
        'type'          => 'text',
        'name'          => 'utm_source',
        'default_value' => null,
        'field_config'  => array_merge( ['dynamic_population' => true], $config ),
    ], $attributes ) );
}

describe( 'dynamic population settings', function (): void {
    it( 'offers the toggle and parameter name in the Advanced tab', function (): void {
        $field = FormField::factory()->make( ['type' => 'hidden'] );

        expect( FieldSettings::find( $field, 'dynamic_population' ) )->toMatchArray( ['input' => 'toggle', 'tab' => 'advanced'] )
            ->and( FieldSettings::find( $field, 'dynamic_parameter' ) )->toMatchArray( ['input' => 'text', 'tab' => 'advanced'] );
    } );

    it( 'is not offered on file or layout fields', function ( string $type ): void {
        $field = FormField::factory()->make( ['type' => $type, 'field_config' => ['dynamic_population' => true]] );

        expect( FieldSettings::find( $field, 'dynamic_population' ) )->toBeNull()
            ->and( $field->allowsDynamicPopulation() )->toBeFalse();
    } )->with( ['file', 'heading', 'html'] );

    it( 'uses the field name when the parameter is blank', function (): void {
        expect( populatedField()->getPopulationParameter() )->toBe( 'utm_source' )
            ->and( populatedField( [], ['dynamic_parameter' => ' src '] )->getPopulationParameter() )->toBe( 'src' )
            ->and( FormField::factory()->make( ['type' => 'text'] )->getPopulationParameter() )->toBeNull();
    } );
} );

describe( 'dynamic population sources', function (): void {
    afterEach( function (): void {
        removeAllFilters( 'ap.forms.fieldValue.utm_source' );
    } );

    it( 'prefers the prefill, then the query string, then the filter, then the default', function (): void {
        addFilter( 'ap.forms.fieldValue.utm_source', fn ( ?string $value ): string => 'from-filter' );

        $field = populatedField( ['default_value' => 'static'] );
        $query = new PopulationContext( query: ['utm_source' => 'from-query'] );
        $none  = new PopulationContext();

        expect( DynamicPopulation::initialValue( $field, $query, ['utm_source' => 'from-prefill'] ) )->toBe( 'from-prefill' )
            ->and( DynamicPopulation::initialValue( $field, $query ) )->toBe( 'from-query' )
            ->and( DynamicPopulation::initialValue( $field, $none ) )->toBe( 'from-filter' );

        removeAllFilters( 'ap.forms.fieldValue.utm_source' );

        expect( DynamicPopulation::initialValue( $field, $none ) )->toBe( 'static' );
    } );

    it( 'passes the resolved default, the field and the context to the filter', function (): void {
        $received = null;

        addFilter( 'ap.forms.fieldValue.utm_source', function ( ?string $value, FormField $field, PopulationContext $context ) use ( &$received ): string {
            $received = [$value, $field->name, $context->ipAddress];

            return (string) $value;
        } );

        DynamicPopulation::initialValue( populatedField( ['default_value' => 'fallback'] ), new PopulationContext( ipAddress: '10.0.0.1' ) );

        expect( $received )->toBe( ['fallback', 'utm_source', '10.0.0.1'] );
    } );

    it( 'ignores the sources when dynamic population is off', function (): void {
        $field = FormField::factory()->make( ['type' => 'text', 'name' => 'utm_source', 'default_value' => 'static', 'field_config' => []] );

        expect( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['utm_source' => 'x'] ), ['utm_source' => 'y'] ) )->toBe( 'static' );
    } );

    it( 'does not populate a read-only field from the client', function (): void {
        $field = populatedField( ['default_value' => 'locked'], ['read_only' => true] );

        expect( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['utm_source' => 'x'] ), ['utm_source' => 'y'] ) )->toBe( 'locked' );
    } );

    it( 'sanitizes hostile input', function ( mixed $hostile, string $expected ): void {
        $context = new PopulationContext( query: ['utm_source' => $hostile] );

        expect( DynamicPopulation::initialValue( populatedField(), $context ) )->toBe( $expected );
    } )->with( [
        'script tag'      => ['<script>alert(1)</script>news', 'alert(1)news'],
        'markup'          => ['<b onclick="x()">bold</b>', 'bold'],
        'control chars'   => ["line\x00one\x1Ftwo", 'lineonetwo'],
        'padding'         => ['   spaced   ', 'spaced'],
        'over the limit'  => [str_repeat( 'a', 2000 ), str_repeat( 'a', DynamicPopulation::MAX_LENGTH )],
    ] );

    it( 'selects the matching option of a choice field by value or label', function (): void {
        $options = ['options' => [['value' => 'us', 'label' => 'United States'], ['value' => 'ca', 'label' => 'Canada']]];
        $field   = populatedField( ['type' => 'select', 'name' => 'country', 'default_value' => 'us'], $options );

        expect( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['country' => 'ca'] ) ) )->toBe( 'ca' )
            ->and( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['country' => 'canada'] ) ) )->toBe( 'ca' )
            ->and( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['country' => 'mexico'] ) ) )->toBe( 'us' );
    } );

    it( 'accepts comma-separated values for a checkbox group', function (): void {
        $options = ['options' => [['value' => 'a', 'label' => 'Apples'], ['value' => 'b', 'label' => 'Bananas'], ['value' => 'c', 'label' => 'Cherries']]];
        $field   = populatedField( ['type' => 'checkbox_group', 'name' => 'fruit'], $options );

        expect( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['fruit' => 'a, cherries,zzz'] ) ) )->toBe( ['a', 'c'] )
            ->and( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['fruit' => ['b', 'a']] ) ) )->toBe( ['b', 'a'] );
    } );

    it( 'falls back to the default when no populated value matches a multi-choice option', function ( string $type ): void {
        $options = ['options' => [['value' => 'a', 'label' => 'Apples'], ['value' => 'b', 'label' => 'Bananas'], ['value' => 'c', 'label' => 'Cherries']]];
        $field   = populatedField( ['type' => $type, 'name' => 'fruit', 'default_value' => 'a, cherries'], $options );
        $bare    = populatedField( ['type' => $type, 'name' => 'fruit'], $options );

        expect( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['fruit' => 'zzz'] ) ) )->toBe( ['a', 'c'] )
            ->and( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['fruit' => 'b'] ) ) )->toBe( ['b'] )
            ->and( DynamicPopulation::initialValue( $bare, new PopulationContext( query: ['fruit' => 'zzz'] ) ) )->toBe( [] );
    } )->with( ['checkbox_group', 'select_multiple'] );

    it( 'reads a checkbox or toggle value as on or off', function (): void {
        $field = populatedField( ['type' => 'toggle', 'name' => 'subscribe'] );

        expect( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['subscribe' => 'yes'] ) ) )->toBeTrue()
            ->and( DynamicPopulation::initialValue( $field, new PopulationContext( query: ['subscribe' => '0'] ) ) )->toBeFalse();
    } );
} );

describe( 'default value merge tags', function (): void {
    it( 'replaces the user, date, ip and page tags', function (): void {
        Carbon::setTestNow( '2026-10-20 12:00:00' );

        $user    = new User( ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'] );
        $context = new PopulationContext(
            user: $user,
            ipAddress: '203.0.113.9',
            embedUrl: 'https://example.com/contact?utm_source=news',
            referer: 'https://search.example/',
            query: ['utm_source' => 'news'],
        );

        expect( MergeTags::resolve( '{user:name} <{user:email}> on {date} from {ip}', $context ) )
            ->toBe( 'Ada Lovelace <ada@example.com> on 2026-10-20 from 203.0.113.9' )
            ->and( MergeTags::resolve( '{embed_url}|{referer}|{query:utm_source}|{query:missing}', $context ) )
            ->toBe( 'https://example.com/contact?utm_source=news|https://search.example/|news|' );

        Carbon::setTestNow();
    } );

    it( 'leaves unknown tags and other braces alone', function (): void {
        expect( MergeTags::resolve( '{user:password} {unknown} {', new PopulationContext() ) )->toBe( '{user:password} {unknown} {' );
    } );

    it( 'blanks user tags for guests', function (): void {
        expect( MergeTags::resolve( '[{user:email}]', new PopulationContext() ) )->toBe( '[]' );
    } );

    it( 'keeps page tags for the client or blanks them, by mode', function (): void {
        $keep  = new PopulationContext( query: ['a' => 'x'], pageTags: PopulationContext::PAGE_TAGS_KEEP );
        $blank = new PopulationContext( query: ['a' => 'x'], pageTags: PopulationContext::PAGE_TAGS_BLANK );

        expect( MergeTags::resolve( '{query:a}/{embed_url}/{referer}', $keep ) )->toBe( '{query:a}/{embed_url}/{referer}' )
            ->and( MergeTags::resolve( '{query:a}/{embed_url}/{referer}', $blank ) )->toBe( '//' );
    } );

    it( 'sanitizes values from the page', function (): void {
        $context = new PopulationContext( query: ['q' => '<img src=x onerror=alert(1)>hi'] );

        expect( MergeTags::resolve( '{query:q}', $context ) )->toBe( 'hi' );
    } );

    it( 'resolves only server tags in a read-only value', function (): void {
        $field = FormField::factory()->make( [
            'type'          => 'text',
            'default_value' => '{date} {query:plan}',
            'field_config'  => ['read_only' => true],
        ] );

        expect( $field->getReadOnlyValue() )->toBe( now()->toDateString() . ' ' );
    } );
} );

describe( 'dynamic population in the renderers', function (): void {
    it( 'populates the Livewire renderer from the prefill and the query string', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'hidden', 'name' => 'utm_source', 'field_config' => ['dynamic_population' => true]] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'ref', 'field_config' => ['dynamic_population' => true, 'dynamic_parameter' => 'code']] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'other', 'default_value' => 'Today is {date}'] );

        Livewire::withQueryParams( ['utm_source' => '<b>news</b>', 'code' => 'FROM-QUERY'] )
            ->test( FormRenderer::class, ['form' => $form, 'prefill' => ['code' => 'FROM-PREFILL']] )
            ->assertSet( 'formData.utm_source', 'news' )
            ->assertSet( 'formData.ref', 'FROM-PREFILL' )
            ->assertSet( 'formData.other', 'Today is ' . now()->toDateString() );
    } );

    it( 'restores the populated values when the form is reset', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'ref', 'field_config' => ['dynamic_population' => true]] );

        Livewire::test( FormRenderer::class, ['form' => $form, 'prefill' => ['ref' => 'ABC']] )
            ->set( 'formData.ref', 'changed' )
            ->call( 'resetForm' )
            ->assertSet( 'formData.ref', 'ABC' );
    } );

    it( 'returns resolved defaults in the API render payload, leaving page tags for the client', function (): void {
        $form = Form::factory()->create( ['is_active' => true] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'when', 'default_value' => '{date} {query:utm_source}'] );
        FormField::factory()->for( $form )->create( ['type' => 'text', 'name' => 'src', 'field_config' => ['dynamic_population' => true]] );

        addFilter( 'ap.forms.fieldValue.src', fn (): string => 'filtered' );

        $fields = collect( $this->getJson( route( 'api.forms.render', $form ) )->assertOk()->json( 'data.fields' ) )->keyBy( 'name' );

        removeAllFilters( 'ap.forms.fieldValue.src' );

        expect( $fields['when']['resolved_default_value'] )->toBe( now()->toDateString() . ' {query:utm_source}' )
            ->and( $fields['when']['dynamic_parameter'] )->toBeNull()
            ->and( $fields['src']['resolved_default_value'] )->toBe( 'filtered' )
            ->and( $fields['src']['dynamic_parameter'] )->toBe( 'src' );
    } );

    it( 'leaves resolved defaults out of the builder payload', function (): void {
        $field = FormField::factory()->create( ['type' => 'text', 'default_value' => '{date}'] );

        expect( ( new FormFieldResource( $field ) )->resolve( Request::create( '/' ) ) )->not->toHaveKey( 'resolved_default_value' )
            ->and( ( new FormFieldResource( $field ) )->withResolvedDefault()->resolve( Request::create( '/' ) ) )->toHaveKey( 'resolved_default_value' );
    } );
} );
