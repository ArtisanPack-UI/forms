<?php

declare( strict_types=1 );

use ArtisanPackUI\Forms\Livewire\FormRenderer;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormStep;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Security\SecurityServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

describe( 'FormRenderer Component', function (): void {
    describe( 'rendering', function (): void {
        it( 'renders the form renderer component', function (): void {
            $form = Form::factory()->create();

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertStatus( 200 );
        } );

        it( 'displays the form description when provided', function (): void {
            $form = Form::factory()->create( [
                'description' => 'Please fill out this form to contact us.',
            ] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Please fill out this form to contact us.' );
        } );

        it( 'displays form fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( [
                'label' => 'Full Name',
                'type'  => 'text',
            ] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Full Name' );
        } );

        it( 'displays the submit button text', function (): void {
            $form = Form::factory()->create( ['submit_button_text' => 'Send Message'] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Send Message' );
        } );

        it( 'shows success message after submission', function (): void {
            $form = Form::factory()->create( ['success_message' => 'Thank you for your submission!'] );

            $component = Livewire::test( FormRenderer::class, ['form' => $form] );
            $component->set( 'isSubmitted', true );

            $component->assertSee( 'Thank you for your submission!' );
        } );
    } );

    describe( 'field types', function (): void {
        it( 'renders a text field', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->text()->create( ['label' => 'Name'] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Name' );
        } );

        it( 'renders an email field', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->email()->create();

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Email Address' );
        } );

        it( 'renders a textarea field', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->textarea()->create( ['label' => 'Message'] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Message' );
        } );

        it( 'renders a select field', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->select()->create( ['label' => 'Choose Option'] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Choose Option' );
        } );

        it( 'renders a multi-select field', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->selectMultiple()->create( ['label' => 'Choose Multiple'] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->assertSee( 'Choose Multiple' );
        } );
    } );

    describe( 'validation', function (): void {
        it( 'validates required fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->required()->create( [
                'name'  => 'name',
                'label' => 'Name',
                'type'  => 'text',
            ] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'honeypot', '' ) // Ensure honeypot is empty
                ->set( 'formLoadedAt', time() - 10 ) // Ensure enough time has passed
                ->set( 'formData.name', '' )
                ->call( 'submit' )
                ->assertHasErrors( ['formData.name' => 'required'] );
        } );

        it( 'validates email fields', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->email()->required()->create( ['name' => 'email'] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'honeypot', '' ) // Ensure honeypot is empty
                ->set( 'formLoadedAt', time() - 10 ) // Ensure enough time has passed
                ->set( 'formData.email', 'invalid-email' )
                ->call( 'submit' )
                ->assertHasErrors( ['formData.email' => 'email'] );
        } );

        it( 'passes validation with valid data', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->required()->create( [
                'name'  => 'name',
                'label' => 'Name',
                'type'  => 'text',
            ] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'formData.name', 'John Doe' )
                ->set( 'formLoadedAt', time() - 10 ) // Simulate time passing
                ->call( 'submit' )
                ->assertHasNoErrors();
        } );
    } );

    describe( 'submission', function (): void {
        it( 'creates a submission on valid form submit', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( [
                'name'  => 'name',
                'label' => 'Name',
                'type'  => 'text',
            ] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'formData.name', 'John Doe' )
                ->set( 'formLoadedAt', time() - 10 ) // Simulate time passing
                ->call( 'submit' )
                ->assertSet( 'isSubmitted', true )
                ->assertDispatched( 'form-submitted' );

            expect( FormSubmission::where( 'form_id', $form->id )->count() )->toBe( 1 );
        } );

        it( 'stores field values in submission', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( [
                'name'  => 'email',
                'label' => 'Email',
                'type'  => 'email',
            ] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'formData.email', 'test@example.com' )
                ->set( 'formLoadedAt', time() - 10 )
                ->call( 'submit' );

            $submission = FormSubmission::where( 'form_id', $form->id )->first();

            expect( $submission )->not->toBeNull()
                ->and( $submission->getValue( 'email' ) )->toBe( 'test@example.com' );
        } );

        it( 'dispatches redirect event when redirect URL is configured', function (): void {
            $form = Form::factory()->create( [
                'redirect_url' => 'https://example.com/thank-you',
            ] );

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'formLoadedAt', time() - 10 )
                ->call( 'submit' )
                ->assertDispatched( 'redirect-to', url: 'https://example.com/thank-you' );
        } );

        it( 'resets form state on resetForm', function (): void {
            $form = Form::factory()->create();
            FormField::factory()->for( $form )->create( [
                'name' => 'name',
                'type' => 'text',
            ] );

            $component = Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'formData.name', 'Test Value' )
                ->set( 'isSubmitted', true )
                ->call( 'resetForm' );

            $component->assertSet( 'isSubmitted', false )
                ->assertSet( 'formData.name', '' )
                ->assertDispatched( 'form-reset' );
        } );
    } );
} );

describe( 'FormRenderer Multi-Step', function (): void {
    beforeEach( function (): void {
        $this->form  = Form::factory()->multiStep()->create();
        $this->step1 = FormStep::factory()->for( $this->form )->create( [
            'title'      => 'Step 1',
            'sort_order' => 1,
        ] );
        $this->step2 = FormStep::factory()->for( $this->form )->create( [
            'title'      => 'Step 2',
            'sort_order' => 2,
        ] );

        // Add fields to each step
        FormField::factory()->for( $this->form )->create( [
            'name'        => 'name',
            'label'       => 'Name',
            'type'        => 'text',
            'step_id'     => $this->step1->id,
            'is_required' => true,
        ] );
        FormField::factory()->for( $this->form )->create( [
            'name'    => 'email',
            'label'   => 'Email',
            'type'    => 'email',
            'step_id' => $this->step2->id,
        ] );
    } );

    it( 'starts at first step', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSet( 'currentStepIndex', 0 );
    } );

    it( 'shows current step fields only', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSee( 'Name' )
            ->assertDontSee( 'Email' );
    } );

    it( 'navigates to next step', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formData.name', 'John Doe' ) // Fill required field
            ->call( 'nextStep' )
            ->assertSet( 'currentStepIndex', 1 )
            ->assertDispatched( 'step-changed' );
    } );

    it( 'validates current step before proceeding', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formData.name', '' ) // Leave required field empty
            ->call( 'nextStep' )
            ->assertSet( 'currentStepIndex', 0 ) // Should stay on step 1
            ->assertHasErrors( ['formData.name'] );
    } );

    it( 'navigates to previous step', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formData.name', 'John Doe' )
            ->call( 'nextStep' )
            ->assertSet( 'currentStepIndex', 1 )
            ->call( 'previousStep' )
            ->assertSet( 'currentStepIndex', 0 );
    } );

    it( 'does not go before first step', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->call( 'previousStep' )
            ->assertSet( 'currentStepIndex', 0 );
    } );

    it( 'does not go past last step with nextStep', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formData.name', 'John Doe' )
            ->call( 'nextStep' ) // Go to step 2
            ->call( 'nextStep' ) // Try to go past
            ->assertSet( 'currentStepIndex', 1 );
    } );

    it( 'calculates progress percentage correctly', function (): void {
        $component = Livewire::test( FormRenderer::class, ['form' => $this->form] );

        // Step 1 of 2 = 50%
        expect( $component->get( 'progressPercentage' ) )->toBe( 50 );

        $component->set( 'formData.name', 'John Doe' )
            ->call( 'nextStep' );

        // Step 2 of 2 = 100%
        expect( $component->get( 'progressPercentage' ) )->toBe( 100 );
    } );

    it( 'computes isFirstStep correctly', function (): void {
        $component = Livewire::test( FormRenderer::class, ['form' => $this->form] );

        expect( $component->get( 'isFirstStep' ) )->toBeTrue();

        $component->set( 'formData.name', 'John Doe' )
            ->call( 'nextStep' );

        expect( $component->get( 'isFirstStep' ) )->toBeFalse();
    } );

    it( 'computes isLastStep correctly', function (): void {
        $component = Livewire::test( FormRenderer::class, ['form' => $this->form] );

        expect( $component->get( 'isLastStep' ) )->toBeFalse();

        $component->set( 'formData.name', 'John Doe' )
            ->call( 'nextStep' );

        expect( $component->get( 'isLastStep' ) )->toBeTrue();
    } );

    it( 'submits from the last step', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formData.name', 'John Doe' )
            ->call( 'nextStep' )
            ->set( 'formData.email', 'john@example.com' )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertSet( 'isSubmitted', true );

        expect( FormSubmission::where( 'form_id', $this->form->id )->count() )->toBe( 1 );
    } );

    it( 'allows step navigation when enabled', function (): void {
        $this->form->update( ['allow_step_navigation' => true] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formData.name', 'John Doe' )
            ->call( 'goToStep', 1 )
            ->assertSet( 'currentStepIndex', 1 );
    } );

    it( 'prevents step navigation when disabled', function (): void {
        $this->form->update( ['allow_step_navigation' => false] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->call( 'goToStep', 1 )
            ->assertSet( 'currentStepIndex', 0 );
    } );
} );

describe( 'FormRenderer Spam Protection', function (): void {
    describe( 'honeypot', function (): void {
        it( 'silently marks spam when honeypot is filled', function (): void {
            $form = Form::factory()->create();

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'honeypot', 'spam-value' )
                ->set( 'formLoadedAt', time() - 10 )
                ->call( 'submit' )
                ->assertSet( 'isSubmitted', true ) // Appears successful
                ->assertDispatched( 'form-submitted', formId: $form->id, isSpam: true );

            // But no submission should be created
            expect( FormSubmission::where( 'form_id', $form->id )->count() )->toBe( 0 );
        } );

        it( 'allows submission when honeypot is empty', function (): void {
            $form = Form::factory()->create();

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'honeypot', '' )
                ->set( 'formLoadedAt', time() - 10 )
                ->call( 'submit' )
                ->assertSet( 'isSubmitted', true );

            expect( FormSubmission::where( 'form_id', $form->id )->count() )->toBe( 1 );
        } );
    } );

    describe( 'timestamp validation', function (): void {
        it( 'silently marks spam when submission is too fast', function (): void {
            $form = Form::factory()->create();

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'formLoadedAt', time() ) // Just loaded
                ->call( 'submit' )
                ->assertSet( 'isSubmitted', true ) // Appears successful
                ->assertDispatched( 'form-submitted', formId: $form->id, isSpam: true );

            // No real submission created
            expect( FormSubmission::where( 'form_id', $form->id )->count() )->toBe( 0 );
        } );

        it( 'allows submission after minimum time has passed', function (): void {
            $form = Form::factory()->create();

            Livewire::test( FormRenderer::class, ['form' => $form] )
                ->set( 'formLoadedAt', time() - 10 ) // 10 seconds ago
                ->call( 'submit' )
                ->assertSet( 'isSubmitted', true );

            expect( FormSubmission::where( 'form_id', $form->id )->count() )->toBe( 1 );
        } );
    } );

    describe( 'rate limiting', function (): void {
        it( 'shows error when rate limited', function (): void {
            $form = Form::factory()->create();

            // Simulate rate limiting by making many attempts
            $component = Livewire::test( FormRenderer::class, ['form' => $form] );

            // First few submissions should work
            for ( $i = 0; $i < 5; $i++ ) {
                $component->set( 'formLoadedAt', time() - 10 )
                    ->call( 'submit' )
                    ->call( 'resetForm' );
            }

            // The 6th should be rate limited
            $component->set( 'formLoadedAt', time() - 10 )
                ->call( 'submit' );

            expect( $component->get( 'errorMessage' ) )->toContain( 'Too many submissions' );
        } );
    } );
} );

describe( 'FormRenderer Conditional Logic', function (): void {
    it( 'initializes hidden fields based on conditional logic', function (): void {
        $form         = Form::factory()->create();
        $triggerField = FormField::factory()->for( $form )->create( [
            'name' => 'show_details',
            'type' => 'checkbox',
        ] );
        FormField::factory()->for( $form )->withConditions()->create( [
            'name'              => 'details',
            'type'              => 'textarea',
            'conditional_logic' => [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [
                    ['field' => 'show_details', 'operator' => 'checked', 'value' => ''],
                ],
            ],
        ] );

        $component = Livewire::test( FormRenderer::class, ['form' => $form] );

        // The conditionalLogicConfig should be available
        expect( $component->get( 'conditionalLogicConfig' ) )->toHaveKey( 'details' );
    } );

    it( 'updates hidden fields when form data changes', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'name'         => 'category',
            'type'         => 'select',
            'field_config' => [
                'options' => [
                    ['value' => 'general', 'label' => 'General'],
                    ['value' => 'support', 'label' => 'Support'],
                ],
            ],
        ] );
        FormField::factory()->for( $form )->create( [
            'name'              => 'support_details',
            'type'              => 'textarea',
            'conditional_logic' => [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [
                    ['field' => 'category', 'operator' => 'equals', 'value' => 'support'],
                ],
            ],
        ] );

        $component = Livewire::test( FormRenderer::class, ['form' => $form] )
            ->set( 'formData.category', 'general' );

        // After updating form data, conditional logic should be re-evaluated
        // The component dispatches updatedFormData which triggers evaluateConditionalLogic
        $component->assertHasNoErrors();
    } );

    it( 'excludes hidden fields from validation', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'name'        => 'category',
            'type'        => 'select',
            'is_required' => true,
        ] );
        FormField::factory()->for( $form )->required()->create( [
            'name'              => 'hidden_required',
            'type'              => 'text',
            'conditional_logic' => [
                'action' => 'show',
                'logic'  => 'all',
                'rules'  => [
                    ['field' => 'category', 'operator' => 'equals', 'value' => 'special'],
                ],
            ],
        ] );

        // Set up component with the hidden field in hiddenFields
        $component = Livewire::test( FormRenderer::class, ['form' => $form] )
            ->set( 'formData.category', 'general' )
            ->set( 'hiddenFields', ['hidden_required' => true] )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' );

        // Should not have validation error for hidden_required since it's hidden
        $component->assertHasNoErrors( ['formData.hidden_required'] );
    } );
} );

describe( 'FormRenderer Default Values', function (): void {
    it( 'initializes fields with default values', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'name'          => 'country',
            'type'          => 'text',
            'default_value' => 'United States',
        ] );

        $component = Livewire::test( FormRenderer::class, ['form' => $form] );

        expect( $component->get( 'formData.country' ) )->toBe( 'United States' );
    } );

    it( 'initializes array fields as empty arrays', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'name'          => 'interests',
            'type'          => 'checkbox_group',
            'default_value' => null,
        ] );

        $component = Livewire::test( FormRenderer::class, ['form' => $form] );

        expect( $component->get( 'formData.interests' ) )->toBe( [] );
    } );
} );

describe( 'FormRenderer File Fields (issues #79, #80)', function (): void {
    beforeEach( function (): void {
        Storage::fake( 'form-uploads' );

        $this->form = Form::factory()->create();
    } );

    it( 'does not seed formData for file fields', function (): void {
        FormField::factory()->for( $this->form )->file()->create( ['name' => 'resume'] );

        $component = Livewire::test( FormRenderer::class, ['form' => $this->form] );

        expect( $component->get( 'formData' ) )->not->toHaveKey( 'resume' );
    } );

    it( 'accepts a required file field once a file is uploaded', function (): void {
        FormField::factory()->for( $this->form )->file()->required()->create( ['name' => 'resume'] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'files.resume', UploadedFile::fake()->create( 'resume.pdf', 100 ) )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );

        $submission = FormSubmission::where( 'form_id', $this->form->id )->first();

        expect( $submission )->not->toBeNull()
            ->and( $submission->uploads )->toHaveCount( 1 );
    } );

    it( 'reports a missing required file under files.*', function (): void {
        FormField::factory()->for( $this->form )->file()->required()->create( ['name' => 'resume', 'label' => 'Resume'] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasErrors( ['files.resume' => 'required'] )
            ->assertSee( 'The Resume field is required.' );
    } );

    it( 'validates an optional file against the builder settings', function ( string $name, int $kilobytes, string $rule ): void {
        // Settings as the 1.6.0 builders save them: field_config only.
        FormField::factory()->for( $this->form )->create( [
            'name'         => 'attachment',
            'type'         => 'file',
            'is_required'  => false,
            'field_config' => ['max_size' => 100, 'allowed_types' => ['pdf']],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'files.attachment', UploadedFile::fake()->create( $name, $kilobytes ) )
            ->call( 'submit' )
            ->assertHasErrors( ['files.attachment' => $rule] )
            ->assertSet( 'isSubmitted', false );
    } )->with( [
        'disallowed type' => ['photo.png', 50, 'mimes'],
        'too large'       => ['report.pdf', 2048, 'max'],
    ] );

    it( 'enforces settings saved by a pre-1.6.0 builder in validation_rules', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'name'             => 'attachment',
            'type'             => 'file',
            'field_config'     => ['max_size' => 5120, 'allowed_types' => ['pdf', 'png']],
            'validation_rules' => ['max_size' => 100, 'allowed_types' => ['pdf']],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'files.attachment', UploadedFile::fake()->create( 'photo.png', 50 ) )
            ->call( 'submit' )
            ->assertHasErrors( ['files.attachment' => 'mimes'] );
    } );

    it( 'renders the accept attribute and size hint from field_config', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'name'         => 'attachment',
            'type'         => 'file',
            'field_config' => ['max_size' => 100, 'allowed_types' => ['pdf', 'docx']],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSeeHtml( 'accept=".pdf,.docx"' )
            ->assertSee( 'Maximum file size: 100 KB' );
    } );
} );

describe( 'FormRenderer Required Checkbox (issue #81)', function (): void {
    beforeEach( function (): void {
        $this->form = Form::factory()->create();
        FormField::factory()->for( $this->form )->required()->create( [
            'name'  => 'terms',
            'label' => 'I agree to the terms',
            'type'  => 'checkbox',
        ] );
    } );

    it( 'rejects a required checkbox that was checked then unchecked', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.terms', true )
            ->set( 'formData.terms', false )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.terms' => 'accepted'] )
            ->assertSee( 'The I agree to the terms field is required.' );

        expect( FormSubmission::where( 'form_id', $this->form->id )->count() )->toBe( 0 );
    } );

    it( 'rejects a required checkbox that was never touched', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.terms' => 'accepted'] );
    } );

    it( 'accepts a checked required checkbox', function (): void {
        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->set( 'formData.terms', true )
            ->call( 'submit' )
            ->assertHasNoErrors()
            ->assertSet( 'isSubmitted', true );
    } );
} );

describe( 'FormRenderer Layout Content (issue #82)', function (): void {
    beforeEach( function (): void {
        // The html partial sanitizes through artisanpack-ui/security's kses().
        $this->app->register( SecurityServiceProvider::class );

        $this->form = Form::factory()->create();
    } );

    it( 'renders layout content from its canonical column', function (): void {
        FormField::factory()->for( $this->form )->create( ['type' => 'heading', 'name' => 'h', 'label' => 'Heading From Label'] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'paragraph',
            'name'         => 'p',
            'label'        => 'Information',
            'field_config' => ['content' => 'Paragraph from content'],
        ] );
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'html',
            'name'         => 'html_block',
            'field_config' => ['content' => '<strong>Html from content</strong>'],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSee( 'Heading From Label' )
            ->assertSee( 'Paragraph from content' )
            ->assertSee( 'Html from content' );
    } );

    it( 'sanitizes html content before rendering it', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'type'         => 'html',
            'name'         => 'html_block',
            'field_config' => ['content' => '<b>Safe</b><script>alert("xss")</script><img src="/a.png" onerror="alert(1)">'],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSeeHtml( '<b>Safe</b>' )
            ->assertDontSeeHtml( '<script>alert("xss")</script>' )
            ->assertDontSeeHtml( 'onerror' );
    } );

    it( 'renders legacy content saved to default_value by the React and Vue builders', function (): void {
        FormField::factory()->for( $this->form )->create( [
            'type'          => 'heading',
            'name'          => 'h',
            'label'         => 'Section Heading',
            'default_value' => 'Legacy heading',
        ] );
        FormField::factory()->for( $this->form )->create( [
            'type'          => 'html',
            'name'          => 'html_block',
            'default_value' => '<em>Legacy html</em>',
        ] );

        Livewire::test( FormRenderer::class, ['form' => $this->form] )
            ->assertSee( 'Legacy heading' )
            ->assertDontSee( 'Section Heading' )
            ->assertSee( 'Legacy html' );
    } );
} );

describe( 'FormRenderer unsupported multiple flags (issue #84)', function (): void {
    it( 'renders a single-value input even when field_config.multiple is set', function ( string $type ): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'         => $type,
            'name'         => 'pick',
            'field_config' => [
                'multiple' => true,
                'options'  => [['value' => 'a', 'label' => 'A']],
            ],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSeeHtml( 'id="field-pick"' )
            ->assertDontSeeHtml( 'multiple' );
    } )->with( ['select', 'file'] );

    it( 'stores one value for a select whose config still has multiple set', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( [
            'type'         => 'select',
            'name'         => 'pick',
            'field_config' => [
                'multiple' => true,
                'options'  => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']],
            ],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->set( 'formData.pick', 'b' )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasNoErrors();

        expect( FormSubmission::query()->firstOrFail()->values->first()->value )->toBe( 'b' );
    } );
} );

describe( 'FormRenderer date field', function (): void {
    it( 'renders the date input as the .input element itself so its text is centred', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->required()->create( [
            'type'             => 'date',
            'name'             => 'starts',
            'label'            => 'Start Date',
            'help_text'        => 'Pick a day',
            'validation_rules' => ['min_date' => '2026-01-01', 'max_date' => '2026-12-31'],
        ] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSeeHtml( 'type="date"' )
            ->assertSeeHtml( 'wire:model.live="formData.starts"' )
            ->assertSeeHtml( 'class="input input-bordered w-full' )
            ->assertSeeHtml( 'min="2026-01-01"' )
            ->assertSeeHtml( 'max="2026-12-31"' )
            ->assertSeeHtml( 'aria-describedby="hint-starts"' )
            ->assertDontSeeHtml( 'aria-invalid' )
            ->assertDontSeeHtml( 'aria-label="Starts"' )
            ->assertSee( 'Start Date' );
    } );

    it( 'marks the date input invalid when it has an error', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->required()->create( ['type' => 'date', 'name' => 'starts'] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->set( 'formLoadedAt', time() - 10 )
            ->call( 'submit' )
            ->assertHasErrors( ['formData.starts' => 'required'] )
            ->assertSeeHtml( 'aria-invalid="true"' )
            ->assertSeeHtml( 'aria-describedby="error-starts hint-starts"' );
    } );

    it( 'gives an unlabelled date input an accessible name', function (): void {
        $form = Form::factory()->create();
        FormField::factory()->for( $form )->create( ['type' => 'date', 'name' => 'start_date', 'label' => ''] );

        Livewire::test( FormRenderer::class, ['form' => $form] )
            ->assertSeeHtml( 'aria-label="Start Date"' );
    } );
} );
