<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\FieldTypes\CompositeFieldType;
use ArtisanPackUI\Forms\Http\Resources\FormRenderResource;
use ArtisanPackUI\Forms\Http\Resources\FormSubmissionValueResource;
use ArtisanPackUI\Forms\Livewire\FormBuilder;
use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use ArtisanPackUI\Forms\Services\ConditionalLogicService;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\DynamicPopulation;
use ArtisanPackUI\Forms\Support\FieldRenderer;
use ArtisanPackUI\Forms\Support\FieldSettings;
use ArtisanPackUI\Forms\Support\PopulationContext;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Livewire\Livewire;
use Tests\Support\AddressFieldType;

/**
 * Guards issue #108: the composite (multi-input) field infrastructure, with
 * a test address type (see Tests\Support\AddressFieldType).
 */
beforeEach( function (): void {
    addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
        $types['test_address'] = AddressFieldType::class;

        return $types;
    } );

    $this->form    = Form::factory()->create( ['is_active' => true] );
    $this->address = FormField::factory()->for( $this->form )->required()->create( [
        'type'  => 'test_address',
        'name'  => 'address',
        'label' => 'Address',
    ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.forms.fieldTypes' );
} );

/**
 * Stores a submission holding an address value.
 *
 * @param  array<string, string>  $parts  The address parts.
 */
function storeAddressSubmission( Form $form, array $parts ): FormSubmission
{
    return app( SubmissionService::class )->create( $form, ['address' => $parts] );
}

describe( 'sub-input definitions', function (): void {
    it( 'merge the type definition with the field\'s settings', function (): void {
        $this->address->update( ['field_config' => ['sub_inputs' => [
            'street'  => ['label' => 'Street address', 'required' => false],
            'line2'   => ['enabled' => true, 'placeholder' => 'Apt, suite'],
            'contact' => ['label' => '   '],
        ]]] );

        $subInputs = $this->address->fresh()->getSubInputs();

        expect( array_keys( $subInputs ) )->toBe( ['street', 'city', 'line2', 'contact'] )
            ->and( $subInputs['street'] )->toMatchArray( ['label' => 'Street address', 'default_label' => 'Street', 'required' => false, 'enabled' => true, 'autocomplete' => 'street-address', 'type' => 'text'] )
            ->and( $subInputs['line2'] )->toMatchArray( ['enabled' => true, 'placeholder' => 'Apt, suite', 'autocomplete' => null] )
            ->and( $subInputs['contact'] )->toMatchArray( ['label' => 'Contact email', 'type' => 'email', 'placeholder' => 'you@example.com'] );
    } );

    it( 'make a field composite only when its handler is a composite type', function (): void {
        addFilter( 'ap.forms.fieldTypes', function ( array $types ): array {
            $types['fake_composite'] = ['label' => 'Fake', 'sub_inputs' => [['key' => 'a', 'label' => 'A']]];

            return $types;
        } );

        expect( $this->address->isComposite() )->toBeTrue()
            ->and( FormField::factory()->make( ['type' => 'fake_composite'] )->isComposite() )->toBeFalse()
            ->and( FormField::factory()->make( ['type' => 'text'] )->getSubInputs() )->toBe( [] );
    } );

    it( 'require the marked inputs of a required field, or all of them when none is marked', function (): void {
        expect( $this->address->getRequiredSubInputKeys() )->toBe( ['street', 'city'] );

        $this->address->update( ['field_config' => ['sub_inputs' => ['street' => ['required' => false], 'city' => ['required' => false]]]] );
        expect( $this->address->fresh()->getRequiredSubInputKeys() )->toBe( ['street', 'city', 'contact'] );

        $this->address->update( ['is_required' => false] );
        expect( $this->address->fresh()->getRequiredSubInputKeys() )->toBe( [] );
    } );

    it( 'take no placeholder, default value, autocomplete or dynamic population of their own', function (): void {
        $keys = array_column( FieldSettings::schema( $this->address ), 'key' );

        expect( $this->address->fieldType()->definition() )->toMatchArray( ['supports_placeholder' => false, 'supports_default_value' => false] )
            ->and( $keys )->toContain( 'sub_label_placement', 'export_format', 'admin_label', 'error_message', 'required_message' )
            ->not->toContain( 'autocomplete', 'dynamic_population', 'no_duplicates', 'read_only' );
    } );

    it( 'start blank, one key per enabled input', function (): void {
        expect( DynamicPopulation::initialValue( $this->address, PopulationContext::trusted() ) )->toBe( ['street' => '', 'city' => '', 'contact' => ''] );
    } );
} );

describe( 'validation', function (): void {
    it( 'nests a rule set under each enabled sub-input', function (): void {
        $rules = app( SubmissionService::class )->buildValidationRules( $this->form->fields()->get() );

        expect( $rules )->toMatchArray( [
            'formData.address'         => ['nullable', 'array'],
            'formData.address.street'  => ['required', 'string', 'max:255'],
            'formData.address.city'    => ['required', 'string', 'max:255'],
            'formData.address.contact' => ['nullable', 'string', 'max:255', 'email'],
        ] )->not->toHaveKey( 'formData.address.line2' );
    } );

    it( 'names the field and the input in its messages', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.address.contact', 'not-an-email' )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.address.street' => 'required', 'formData.address.city' => 'required', 'formData.address.contact' => 'email'] )
            ->assertSee( 'Address: Street is required.' )
            ->assertSee( 'Address: Contact email must be a valid email address.' )
            ->assertSee( 'Please fix 3 highlighted parts of Address.' );
    } );

    it( 'applies the custom required message to each input', function (): void {
        $this->address->update( ['field_config' => ['required_message' => 'We need your full address.']] );

        expect( $this->address->fresh()->buildValidationMessages() )->toMatchArray( ['street.required' => 'We need your full address.', 'city.required' => 'We need your full address.'] );
    } );

    it( 'validates through the API with nested error keys', function (): void {
        $this->postJson( route( 'api.forms.submit', $this->form ), [
            'data'            => ['address' => ['street' => '1 Main St']],
            '_form_loaded_at' => time() - 10,
        ] )->assertUnprocessable()->assertJsonValidationErrors( 'data.address.city' );
    } );
} );

describe( 'storage and display', function (): void {
    it( 'stores the enabled inputs as an associative value array', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.address.street', '  1 Main St ' )
            ->set( 'formData.address.city', 'Springfield' )
            ->set( 'formData.address.line2', 'Hidden input' )
            ->call( 'submit' )
            ->assertHasNoErrors();

        $value = $this->form->submissions()->first()->values()->first();

        expect( $value->value_array )->toBe( ['street' => '1 Main St', 'city' => 'Springfield', 'contact' => ''] )
            ->and( $value->value )->toBeNull()
            ->and( $value->display_value )->toBe( '1 Main St, Springfield' );
    } );

    it( 'keeps the stored order when the field was deleted', function (): void {
        $value = new FormSubmissionValue( ['field_type' => 'test_address', 'value_array' => ['city' => 'Springfield', 'street' => '1 Main St']] );

        expect( $value->display_value )->toBe( 'Springfield, 1 Main St' );
    } );

    it( 'gives webhooks, the JSON export and the API a structured object', function (): void {
        $submission = storeAddressSubmission( $this->form, ['street' => '1 Main St', 'city' => 'Springfield'] );

        expect( $submission->fresh()->data_array['address'] )->toBe( ['street' => '1 Main St', 'city' => 'Springfield', 'contact' => ''] )
            ->and( app( ExportService::class )->exportToJson( $this->form, $this->form->submissions()->get() )[0]['data']['address'] )->toBe( ['street' => '1 Main St', 'city' => 'Springfield', 'contact' => ''] );

        $resource = ( new FormSubmissionValueResource( $submission->values()->first() ) )->toArray( Request::create( '/' ) );

        expect( $resource['value_array'] )->toBe( ['street' => '1 Main St', 'city' => 'Springfield', 'contact' => ''] )
            ->and( $resource['display_value'] )->toBe( '1 Main St, Springfield' );
    } );
} );

describe( 'export', function (): void {
    it( 'writes one column per enabled input by default', function (): void {
        $submission = storeAddressSubmission( $this->form, ['street' => '1 Main St', 'city' => 'Springfield', 'contact' => 'a@b.co'] );
        $export     = app( ExportService::class );

        expect( $export->buildHeaders( $this->form ) )->toContain( 'Address (Street)', 'Address (City)', 'Address (Contact email)' )
            ->not->toContain( 'Address (Line 2)' )
            ->and( $export->buildRow( $this->form, $submission->fresh(), $this->form->fields()->get() ) )->toContain( '1 Main St', 'Springfield', 'a@b.co' );
    } );

    it( 'writes one formatted column when set to single', function (): void {
        $this->address->update( ['field_config' => ['export_format' => 'single']] );
        $submission = storeAddressSubmission( $this->form, ['street' => '1 Main St', 'city' => 'Springfield'] );
        $export     = app( ExportService::class );

        expect( $export->buildHeaders( $this->form ) )->toContain( 'Address' )
            ->and( $export->buildRow( $this->form, $submission->fresh(), $this->form->fields()->get() ) )->toContain( '1 Main St, Springfield' );
    } );

    it( 'redacts every column of a redacted value', function (): void {
        $submission = storeAddressSubmission( $this->form, ['street' => '1 Main St', 'city' => 'Springfield'] );
        $submission->values()->update( ['value_array' => null, 'redacted_at' => now()] );

        expect( $this->address->fieldType()->exportValues( $this->address, $submission->values()->first() ) )->toBe( ['[Redacted]', '[Redacted]', '[Redacted]'] );
    } );
} );

describe( 'merge tags', function (): void {
    it( 'offer the formatted value and each input', function (): void {
        expect( app( NotificationService::class )->getAvailablePlaceholders( $this->form ) )->toMatchArray( [
            '{address}'         => 'Address',
            '{address:street}'  => 'Address: Street',
            '{address:city}'    => 'Address: City',
            '{address:contact}' => 'Address: Contact email',
        ] )->not->toHaveKey( '{address:line2}' );
    } );

    it( 'are replaced in notification templates', function (): void {
        $submission = storeAddressSubmission( $this->form, ['street' => '1 Main St', 'city' => 'Springfield'] );

        expect( app( NotificationService::class )->parseTemplate( 'Ship to {address} ({address:city}) {address:contact}.', $submission->fresh() ) )
            ->toBe( 'Ship to 1 Main St, Springfield (Springfield) .' );
    } );
} );

describe( 'conditional logic', function (): void {
    beforeEach( function (): void {
        $this->notes = FormField::factory()->for( $this->form )->create( [
            'type'              => 'text',
            'name'              => 'notes',
            'sort_order'        => 2,
            'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => $this->address->uuid . '.city', 'operator' => 'equals', 'value' => 'Springfield']]],
        ] );
    } );

    it( 'tests one input by name or UUID', function (): void {
        $service = app( ConditionalLogicService::class )->withFields( $this->form->fields()->get() );

        expect( $service->evaluateRule( ['field' => 'address.city', 'operator' => 'equals', 'value' => 'Springfield'], ['address' => ['city' => 'Springfield']] ) )->toBeTrue()
            ->and( $service->evaluateRule( ['field' => $this->address->uuid . '.city', 'operator' => 'equals', 'value' => 'Springfield'], ['address' => ['city' => 'Shelbyville']] ) )->toBeFalse()
            ->and( $service->evaluateRule( ['field' => 'address.city', 'operator' => 'is_empty'], ['address' => 'not-an-array'] ) )->toBeTrue();
    } );

    it( 'shows and hides fields in the Livewire renderer', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSet( 'hiddenFields.notes', true )
            ->set( 'formData.address.city', 'Springfield' )
            ->assertSet( 'hiddenFields.notes', false );
    } );

    it( 'decides notifications from the stored value', function (): void {
        $notification = FormNotification::factory()->for( $this->form )->create( [
            'conditional_logic' => ['action' => 'send', 'logic' => 'all', 'rules' => [['field' => 'address.city', 'operator' => 'equals', 'value' => 'Springfield']]],
        ] );
        $service = app( NotificationService::class );

        expect( $service->shouldSendNotification( $notification, storeAddressSubmission( $this->form, ['street' => 'x', 'city' => 'Springfield'] )->fresh() ) )->toBeTrue()
            ->and( $service->shouldSendNotification( $notification, storeAddressSubmission( $this->form, ['street' => 'x', 'city' => 'Ogdenville'] )->fresh() ) )->toBeFalse();
    } );

    it( 'lists each enabled input as a target', function (): void {
        expect( $this->address->getConditionTargets() )->toBe( [
            ['name' => 'address.street', 'label' => 'Address: Street', 'type' => 'text', 'uuid' => $this->address->uuid . '.street', 'options' => []],
            ['name' => 'address.city', 'label' => 'Address: City', 'type' => 'text', 'uuid' => $this->address->uuid . '.city', 'options' => []],
            ['name' => 'address.contact', 'label' => 'Address: Contact email', 'type' => 'text', 'uuid' => $this->address->uuid . '.contact', 'options' => []],
        ] )->and( $this->notes->getConditionTargets() )->toHaveCount( 1 );
    } );

    it( 'keeps rules on an input when references are cleaned up', function (): void {
        $logic = app( ConditionalLogicService::class )->cleanupDeletedFieldReferences( $this->notes->conditional_logic, $this->form->fields()->get() );

        expect( $logic['rules'] )->toHaveCount( 1 );
    } );
} );

describe( 'rendering', function (): void {
    it( 'groups the inputs in a fieldset with the label as its legend', function (): void {
        $html = FieldRenderer::render( $this->address, ['street' => '', 'city' => '', 'contact' => ''] );

        expect( $html )->toContain( '<fieldset' )
            ->toContain( '<legend' )
            ->toContain( 'wire:model.live="formData.address.street"' )
            ->toContain( 'id="field-address-street"' )
            ->toContain( 'for="field-address-street"' )
            ->toContain( 'autocomplete="street-address"' )
            ->toContain( 'autocomplete="address-level2"' )
            ->toContain( 'type="email"' )
            ->toContain( 'placeholder="you@example.com"' )
            ->not->toContain( 'formData.address.line2' )
            ->and( substr_count( $html, 'required aria-required="true"' ) )->toBe( 2 );
    } );

    it( 'places the sub-labels below, above or visually hidden', function (): void {
        $below = FieldRenderer::render( $this->address, [] );

        $this->address->update( ['field_config' => ['sub_label_placement' => 'above']] );
        $above = FieldRenderer::render( $this->address->fresh(), [] );

        $this->address->update( ['field_config' => ['sub_label_placement' => 'hidden']] );
        $hidden = FieldRenderer::render( $this->address->fresh(), [] );

        expect( strpos( $below, 'for="field-address-street"' ) )->toBeGreaterThan( strpos( $below, 'id="field-address-street"' ) )
            ->and( strpos( $above, 'for="field-address-street"' ) )->toBeLessThan( strpos( $above, 'id="field-address-street"' ) )
            ->and( $hidden )->toMatch( '/<label for="field-address-street" class="label text-sm sr-only">/' );
    } );

    it( 'shows each input\'s error and a field-level summary', function (): void {
        $this->address->update( ['help_text' => 'Where we ship to.'] );
        $errors = new MessageBag( ['formData.address.city' => ['Address: City is required.']] );
        $html   = FieldRenderer::render( $this->address->fresh(), [], null, FieldRenderer::subInputErrors( $this->address, $errors ) );

        expect( $html )->toContain( 'aria-invalid="true" aria-describedby="error-address-city"' )
            ->toContain( 'id="error-address-city"' )
            ->toContain( 'Address: City is required.' )
            ->toContain( 'id="error-address"' )
            ->toContain( 'Please fix 1 highlighted part of Address.' )
            ->toContain( 'aria-describedby="hint-address error-address"' );
    } );

    it( 'sends the sub-inputs to the React and Vue renderers', function (): void {
        $fields = ( new FormRenderResource( $this->form->load( ['fields', 'steps.fields'] ) ) )->toArray( Request::create( '/' ) )['fields']->resolve();

        expect( $fields[0]['sub_inputs'] )->toHaveCount( 4 )
            ->and( $fields[0]['sub_inputs'][0] )->toBe( [
                'key'           => 'street',
                'label'         => 'Street',
                'default_label' => 'Street',
                'placeholder'   => null,
                'enabled'       => true,
                'required'      => true,
                'autocomplete'  => 'street-address',
                'type'          => 'text',
            ] )
            ->and( $fields[0]['required_sub_inputs'] )->toBe( ['street', 'city'] )
            ->and( $fields[0]['sub_label_placement'] )->toBe( 'below' )
            ->and( $fields[0]['resolved_default_value'] )->toBe( ['street' => '', 'city' => '', 'contact' => ''] );
    } );
} );

describe( 'builder', function (): void {
    it( 'edits each sub-input from the Livewire builder', function (): void {
        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $this->address->uuid )
            ->assertSee( 'Sub-Label' )
            ->call( 'updateSubInput', 'line2', 'enabled', true )
            ->call( 'updateSubInput', 'street', 'label', '  Street address  ' )
            ->call( 'updateSubInput', 'city', 'placeholder', 'Town' )
            ->call( 'updateSubInput', 'contact', 'required', '1' )
            ->call( 'updateSubInput', 'street', 'bogus', 'x' )
            ->call( 'updateSubInput', 'nope', 'label', 'x' );

        expect( $this->address->fresh()->getConfig( 'sub_inputs' ) )->toBe( [
            'line2'   => ['enabled' => true],
            'street'  => ['label' => 'Street address'],
            'city'    => ['placeholder' => 'Town'],
            'contact' => ['required' => true],
        ] );
    } );

    it( 'clears a blank sub-label back to the default', function (): void {
        $this->address->update( ['field_config' => ['sub_inputs' => ['street' => ['label' => 'Road']]]] );

        $config = CompositeFieldType::updateSubInputConfig( $this->address->fresh(), 'street', 'label', '  ' );

        expect( $config['sub_inputs']['street'] )->toBe( [] );
    } );

    it( 'keeps at least one input enabled', function (): void {
        $this->address->update( ['field_config' => ['sub_inputs' => ['street' => ['enabled' => false], 'contact' => ['enabled' => false]]]] );

        expect( CompositeFieldType::updateSubInputConfig( $this->address->fresh(), 'city', 'enabled', false ) )->toBeNull()
            ->and( CompositeFieldType::updateSubInputConfig( $this->address->fresh(), 'line2', 'enabled', false ) )->not->toBeNull();
    } );

    it( 'lists each input in the conditional logic builder', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'text', 'name' => 'notes', 'sort_order' => 2] );
        $notes = $this->form->fields()->where( 'name', 'notes' )->first();

        Livewire::test( FormBuilder::class, ['form' => $this->form] )
            ->call( 'selectField', $notes->uuid )
            ->assertSeeHtml( 'address.street' )
            ->assertSeeHtml( 'Address: City' );
    } );
} );
