<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Contracts\FieldType;
use ArtisanPackUI\Forms\FieldTypes\AbstractFieldType;
use ArtisanPackUI\Forms\FieldTypes\ArrayFieldType;
use ArtisanPackUI\Forms\FieldTypes\CheckboxFieldType;
use ArtisanPackUI\Forms\FieldTypes\TextFieldType;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use Illuminate\Support\Facades\Log;

/**
 * A composite test type: an address with a nested `street` input, two export
 * columns and a merge tag per part.
 */
class AddressTestFieldType extends AbstractFieldType
{
    public function rules( FormField $field ): array
    {
        return array_merge( parent::rules( $field ), [
            'street' => ['required', 'string', 'max:100'],
        ] );
    }

    public function normalize( FormField $field, mixed $value ): mixed
    {
        return is_array( $value ) ? array_map( 'trim', $value ) : $value;
    }

    public function exportColumns( FormField $field ): array
    {
        return [$field->label . ' (Street)', $field->label . ' (City)'];
    }

    public function exportValues( FormField $field, ?FormSubmissionValue $value ): array
    {
        $parts = $value?->value_array ?? [];

        return [$parts['street'] ?? '', $parts['city'] ?? ''];
    }

    public function mergeTags( FormField $field ): array
    {
        return [
            '{' . $field->name . '.street}' => $field->label . ' street',
            '{' . $field->name . '.city}'   => $field->label . ' city',
        ];
    }

    public function view(): string
    {
        return 'forms-test::address';
    }

    protected function defineType(): array
    {
        return [
            'label'      => 'Address',
            'icon'       => 'o-map',
            'category'   => 'advanced',
            'sub_inputs' => ['street', 'city'],
        ];
    }

    protected function typeRules( FormField $field ): array
    {
        return ['array'];
    }

    protected function typeMessages( FormField $field ): array
    {
        return ['street.required' => 'Please enter a street.'];
    }
}

describe( 'field type registry', function (): void {
    it( 'has a handler for every built-in type', function (): void {
        foreach ( FieldTypes::HANDLERS as $type => $class ) {
            $handler = FieldTypes::handler( $type );

            expect( $handler )->toBeInstanceOf( $class )
                ->and( $handler->key() )->toBe( $type )
                ->and( FieldTypes::getTypeConfig( $type ) )->toHaveKeys( ['label', 'settings', 'sub_inputs'] );
        }
    } );

    it( 'lists every built-in type in exactly one category', function (): void {
        $categorized = array_merge( ...array_values( FieldTypes::CATEGORY_FIELDS ) );

        expect( $categorized )->toEqualCanonicalizing( array_keys( FieldTypes::HANDLERS ) )
            ->and( array_keys( FieldTypes::TYPE_METADATA ) )->toEqualCanonicalizing( array_keys( FieldTypes::HANDLERS ) );
    } );

    it( 'registers a handler class through the filter', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['address'] = AddressTestFieldType::class;

            return $types;
        } );

        $handler = FieldTypes::handler( 'address' );

        expect( $handler )->toBeInstanceOf( AddressTestFieldType::class )
            ->and( $handler->key() )->toBe( 'address' )
            ->and( FieldTypes::typeExists( 'address' ) )->toBeTrue()
            ->and( FieldTypes::getTypeConfig( 'address' ) )->toMatchArray( ['label' => 'Address', 'sub_inputs' => ['street', 'city']] );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'registers a handler instance through the filter', function (): void {
        $instance = new AddressTestFieldType( 'location' );

        addFilter( 'ap.forms.fieldTypes', function ( array $types ) use ( $instance ): array {
            $types['location'] = $instance;

            return $types;
        } );

        expect( FieldTypes::handler( 'location' ) )->toBe( $instance );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'still accepts an array definition and its type_validation', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['score'] = ['label' => 'Score', 'category' => 'advanced', 'type_validation' => 'integer'];

            return $types;
        } );

        $field = FormField::factory()->create( ['type' => 'score'] );

        expect( FieldTypes::handler( 'score' ) )->toBeInstanceOf( ArrayFieldType::class )
            ->and( $field->buildValidationRules() )->toBe( ['nullable', 'integer'] );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'keeps a filter edit to a built-in definition but the built-in behaviour', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['email']['label'] = 'E-mail address';

            return $types;
        } );

        $field = FormField::factory()->create( ['type' => 'email'] );

        expect( FieldTypes::getTypeConfig( 'email' )['label'] )->toBe( 'E-mail address' )
            ->and( FieldTypes::handler( 'email' )->definition()['label'] )->toBe( 'E-mail address' )
            ->and( $field->buildValidationRules() )->toBe( ['nullable', 'email'] );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'ignores a registered class that is not a handler', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['broken'] = stdClass::class;

            return $types;
        } );

        expect( FieldTypes::getTypes() )->not->toHaveKey( 'broken' )
            ->and( FieldTypes::handler( 'broken' ) )->toBeInstanceOf( ArrayFieldType::class );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'falls back to string validation for an unknown type', function (): void {
        $field = FormField::factory()->create( ['type' => 'mystery'] );

        expect( FieldTypes::handler( 'mystery' ) )->toBeInstanceOf( FieldType::class )
            ->and( $field->buildValidationRules() )->toBe( ['nullable', 'string'] );
    } );

    it( 'keeps checkbox behaviour for the unregistered toggle type', function (): void {
        $field = FormField::factory()->required()->create( ['type' => 'toggle'] );

        expect( FieldTypes::handler( 'toggle' ) )->toBeInstanceOf( CheckboxFieldType::class )
            ->and( FieldTypes::typeExists( 'toggle' ) )->toBeFalse()
            ->and( $field->buildValidationRules() )->toBe( ['accepted', 'boolean'] );
    } );

    it( 'gives a handler instance registered without a key the registry key', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['address'] = new AddressTestFieldType();

            return $types;
        } );

        $handler = FieldTypes::handler( 'address' );

        expect( $handler->key() )->toBe( 'address' )
            ->and( FieldTypes::handler( 'address' ) )->toBeInstanceOf( AddressTestFieldType::class );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'logs a registered class that is not a handler only once', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['broken_once'] = stdClass::class;

            return $types;
        } );

        Log::spy();

        FieldTypes::getTypes();
        FieldTypes::handler( 'broken_once' );
        FieldTypes::handler( 'broken_once' );

        Log::shouldHaveReceived( 'warning' )->once();

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'shares the built-in handlers instead of rebuilding them on every lookup', function (): void {
        expect( FieldTypes::handler( 'email' ) )->toBe( FieldTypes::handler( 'email' ) );

        addFilter( 'ap.forms.fieldTypes', fn ( array $types ): array => $types );

        expect( FieldTypes::handler( 'email' ) )->toBe( FieldTypes::handler( 'email' ) );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'keeps the handler on the field model until its type changes', function (): void {
        $field   = FormField::factory()->make( ['type' => 'email'] );
        $handler = $field->fieldType();

        expect( $field->fieldType() )->toBe( $handler );

        $field->type = 'url';

        expect( $field->fieldType()->key() )->toBe( 'url' );
    } );

    it( 'leaves the cached handler out when the field is serialized', function (): void {
        $field = FormField::factory()->make( ['type' => 'email'] );
        $field->fieldType();

        $restored = unserialize( serialize( $field ) );

        expect( ( fn (): mixed => $this->resolvedFieldType )->call( $restored ) )->toBeNull()
            ->and( $restored->fieldType() )->toBeInstanceOf( FieldType::class )
            ->and( $restored->type )->toBe( 'email' );
    } );

    it( 'builds definitions in the translator locale', function (): void {
        app( 'translator' )->addLines( ['*.Email' => 'Courriel'], 'fr' );

        config( ['app.locale' => 'en'] );
        app( 'translator' )->setLocale( 'fr' );

        expect( FieldTypes::getTypeConfig( 'email' )['label'] )->toBe( 'Courriel' );

        app( 'translator' )->setLocale( 'en' );

        expect( FieldTypes::getTypeConfig( 'email' )['label'] )->toBe( 'Email' );
    } );

    it( 'turns an array type\'s validation_options into builder settings', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['sku'] = ['label' => 'SKU', 'category' => 'advanced', 'validation_options' => ['min', 'max', 'pattern', 'unknown']];

            return $types;
        } );

        $settings = FieldTypes::handler( 'sku' )->definition()['settings'];

        expect( array_column( $settings, 'key' ) )->toBe( ['min', 'max', 'pattern'] )
            ->and( array_unique( array_column( $settings, 'tab' ) ) )->toBe( ['validation'] );

        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'adds no settings to built-in types from their validation_options', function (): void {
        foreach ( FieldTypes::HANDLERS as $type => $class ) {
            $definition = FieldTypes::handler( $type )->definition();
            $keys       = array_column( $definition['settings'], 'key' );

            expect( $keys )->toBe( array_values( array_unique( $keys ) ), "{$type} repeats a setting" );

            foreach ( $definition['validation_options'] ?? [] as $option ) {
                expect( $keys )->toContain( $option );
            }
        }
    } );

    it( 'resolves the view a field renders with from its handler', function (): void {
        expect( FieldTypes::handler( 'text' )->view() )->toBe( 'forms::components.fields.text' )
            ->and( ( new TextFieldType( 'short_text' ) )->view() )->toBe( 'forms::components.fields.short_text' );
    } );
} );

describe( 'a custom handler routes through every consumer', function (): void {
    beforeEach( function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['address'] = AddressTestFieldType::class;

            return $types;
        } );

        $this->form  = Form::factory()->create();
        $this->field = FormField::factory()->for( $this->form )->create( [
            'type'       => 'address',
            'name'       => 'home',
            'label'      => 'Home',
            'sort_order' => 1,
        ] );
    } );

    afterEach( function (): void {
        removeAllFilters( 'ap.forms.fieldTypes' );
    } );

    it( 'validates nested inputs under the field key', function (): void {
        $service = app( SubmissionService::class );

        expect( $service->buildValidationRules( collect( [$this->field] ) ) )->toBe( [
            'formData.home'        => ['nullable', 'array'],
            'formData.home.street' => ['required', 'string', 'max:100'],
        ] )->and( $service->buildValidationMessages( collect( [$this->field] ) ) )->toBe( [
            'formData.home.street.required' => 'Please enter a street.',
        ] );
    } );

    it( 'normalizes the value before storing it', function (): void {
        $submission = app( SubmissionService::class )->create( $this->form, [
            'home' => ['street' => ' 1 Main St ', 'city' => ' Springfield '],
        ] );

        expect( $submission->values()->first()->value_array )->toBe( ['street' => '1 Main St', 'city' => 'Springfield'] );
    } );

    it( 'exports one column per part', function (): void {
        $submission = FormSubmission::factory()->for( $this->form )->create();
        FormSubmissionValue::factory()->create( [
            'submission_id' => $submission->id,
            'field_id'      => $this->field->id,
            'field_name'    => 'home',
            'field_type'    => 'address',
            'value'         => null,
            'value_array'   => ['street' => '1 Main St', 'city' => 'Springfield'],
        ] );

        $service = app( ExportService::class );
        $headers = $service->buildHeaders( $this->form );
        $row     = $service->buildRow( $this->form, $submission->load( 'values' ), $this->form->fields()->get() );

        expect( array_slice( $headers, 3, 2 ) )->toBe( ['Home (Street)', 'Home (City)'] )
            ->and( array_slice( $row, 3, 2 ) )->toBe( ['1 Main St', 'Springfield'] )
            ->and( count( $row ) )->toBe( count( $headers ) );
    } );

    it( 'offers the handler merge tags', function (): void {
        $placeholders = app( NotificationService::class )->getAvailablePlaceholders( $this->form );

        expect( $placeholders )->toMatchArray( [
            '{home.street}' => 'Home street',
            '{home.city}'   => 'Home city',
        ] )->and( $placeholders )->not->toHaveKey( '{home}' );
    } );

    it( 'renders with the handler view', function (): void {
        app( 'view' )->addNamespace( 'forms-test', __DIR__ . '/views' );

        expect( FieldRenderer::render( $this->field ) )->toContain( 'address-field-home' );
    } );
} );
