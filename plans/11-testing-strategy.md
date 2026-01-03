# Testing Strategy

**Purpose:** Define the test coverage plan including unit tests, feature tests, and test helpers.

---

## Overview

The forms package uses Pest for testing with comprehensive coverage across:

- **Unit Tests** - Individual class methods and utilities
- **Feature Tests** - Full request/response cycles and Livewire components
- **Model Factories** - Realistic test data generation

---

## Directory Structure

```
tests/
├── Feature/
│   ├── FormBuilderTest.php
│   ├── FormRendererTest.php
│   ├── SubmissionTest.php
│   ├── NotificationTest.php
│   ├── ConditionalLogicTest.php
│   ├── MultiStepFormTest.php
│   ├── ExportTest.php
│   └── IntegrationHooksTest.php
├── Unit/
│   ├── Models/
│   │   ├── FormTest.php
│   │   ├── FormFieldTest.php
│   │   ├── FormSubmissionTest.php
│   │   └── FormNotificationTest.php
│   ├── Services/
│   │   ├── SubmissionServiceTest.php
│   │   ├── ValidationServiceTest.php
│   │   ├── NotificationServiceTest.php
│   │   └── ExportServiceTest.php
│   └── FieldTypes/
│       ├── TextFieldTest.php
│       ├── EmailFieldTest.php
│       └── FileFieldTest.php
├── Pest.php
└── TestCase.php
```

---

## Test Case Setup

```php
<?php

namespace Tests;

use ArtisanPackUI\Forms\FormsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FormsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
```

---

## Model Factories

### FormFactory

```php
<?php

namespace Database\Factories;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->optional()->paragraph(),
            'submit_button_text' => 'Submit',
            'success_message' => 'Thank you for your submission!',
            'redirect_url' => null,
            'settings' => [
                'spam_protection' => ['honeypot' => true, 'rate_limit' => true],
            ],
            'is_multi_step' => false,
            'show_progress_bar' => true,
            'allow_step_navigation' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function multiStep(): static
    {
        return $this->state(fn () => ['is_multi_step' => true])
            ->afterCreating(function (Form $form) {
                FormStep::factory()->count(3)->create(['form_id' => $form->id]);
            });
    }

    public function withFields(int $count = 3): static
    {
        return $this->afterCreating(function (Form $form) use ($count) {
            FormField::factory()->count($count)->create(['form_id' => $form->id]);
        });
    }

    public function withNotifications(): static
    {
        return $this->afterCreating(function (Form $form) {
            FormNotification::factory()->admin()->create(['form_id' => $form->id]);
            FormNotification::factory()->autoresponder()->create(['form_id' => $form->id]);
        });
    }
}
```

### FormFieldFactory

```php
<?php

namespace Database\Factories;

use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['text', 'email', 'textarea', 'select', 'checkbox']);

        return [
            'form_id' => FormFactory::new(),
            'step_id' => null,
            'uuid' => Str::uuid()->toString(),
            'name' => $this->faker->unique()->word(),
            'type' => $type,
            'label' => ucfirst($this->faker->word()),
            'placeholder' => $this->faker->optional()->sentence(2),
            'help_text' => $this->faker->optional()->sentence(),
            'is_required' => $this->faker->boolean(30),
            'validation_rules' => [],
            'field_config' => $this->getDefaultConfig($type),
            'default_value' => null,
            'conditional_logic' => null,
            'width' => 'full',
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    protected function getDefaultConfig(string $type): array
    {
        return match ($type) {
            'select', 'radio', 'checkbox_group' => [
                'options' => [
                    ['value' => 'option1', 'label' => 'Option 1'],
                    ['value' => 'option2', 'label' => 'Option 2'],
                    ['value' => 'option3', 'label' => 'Option 3'],
                ],
            ],
            'file' => [
                'allowed_types' => ['pdf', 'jpg', 'png'],
                'max_size' => 5120,
            ],
            default => [],
        };
    }

    public function text(): static
    {
        return $this->state(fn () => ['type' => 'text', 'field_config' => []]);
    }

    public function email(): static
    {
        return $this->state(fn () => ['type' => 'email', 'name' => 'email', 'label' => 'Email']);
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    public function withConditions(string $targetUuid, string $operator = 'equals', mixed $value = 'yes'): static
    {
        return $this->state(fn () => [
            'conditional_logic' => [
                'action' => 'show',
                'logic' => 'all',
                'rules' => [
                    ['field_uuid' => $targetUuid, 'operator' => $operator, 'value' => $value],
                ],
            ],
        ]);
    }
}
```

### FormSubmissionFactory

```php
<?php

namespace Database\Factories;

use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    public function definition(): array
    {
        return [
            'form_id' => FormFactory::new(),
            'submission_number' => 'FORM-' . $this->faker->year() . '-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'page_url' => $this->faker->url(),
            'referrer_url' => $this->faker->optional()->url(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'is_read' => false,
            'is_spam' => false,
            'is_starred' => false,
            'admin_notes' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['is_read' => true]);
    }

    public function spam(): static
    {
        return $this->state(fn () => ['is_spam' => true]);
    }

    public function starred(): static
    {
        return $this->state(fn () => ['is_starred' => true]);
    }

    public function withValues(array $values = []): static
    {
        return $this->afterCreating(function (FormSubmission $submission) use ($values) {
            foreach ($values as $fieldName => $value) {
                $submission->values()->create([
                    'field_name' => $fieldName,
                    'field_label' => ucfirst($fieldName),
                    'field_type' => 'text',
                    'value' => $value,
                ]);
            }
        });
    }
}
```

---

## Unit Tests

### Model Tests

```php
<?php

// tests/Unit/Models/FormTest.php

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;

it('generates slug from name on creation', function () {
    $form = Form::factory()->create(['name' => 'Contact Us Form', 'slug' => null]);

    expect($form->slug)->toBe('contact-us-form');
});

it('has many fields', function () {
    $form = Form::factory()->withFields(3)->create();

    expect($form->fields)->toHaveCount(3);
});

it('can duplicate itself with fields and notifications', function () {
    $form = Form::factory()
        ->withFields(3)
        ->withNotifications()
        ->create();

    $clone = $form->duplicate();

    expect($clone->name)->toContain('(Copy)')
        ->and($clone->slug)->not->toBe($form->slug)
        ->and($clone->is_active)->toBeFalse()
        ->and($clone->fields)->toHaveCount(3)
        ->and($clone->notifications)->toHaveCount(2);
});

it('counts unread submissions', function () {
    $form = Form::factory()->create();
    FormSubmission::factory()->count(3)->create(['form_id' => $form->id]);
    FormSubmission::factory()->read()->count(2)->create(['form_id' => $form->id]);

    expect($form->unread_submissions_count)->toBe(3);
});
```

### Service Tests

```php
<?php

// tests/Unit/Services/ValidationServiceTest.php

use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ValidationService;

it('builds required rule when field is required', function () {
    $field = FormField::factory()->required()->make(['type' => 'text']);

    $rules = $field->buildValidationRules();

    expect($rules)->toContain('required');
});

it('builds email rule for email fields', function () {
    $field = FormField::factory()->email()->make();

    $rules = $field->buildValidationRules();

    expect($rules)->toContain('email');
});

it('builds file rules with mime types', function () {
    $field = FormField::factory()->make([
        'type' => 'file',
        'field_config' => [
            'allowed_types' => ['pdf', 'doc'],
            'max_size' => 5120,
        ],
    ]);

    $rules = $field->buildValidationRules();

    expect($rules)
        ->toContain('file')
        ->toContain('mimes:pdf,doc')
        ->toContain('max:5120');
});
```

---

## Feature Tests

### Form Builder Tests

```php
<?php

// tests/Feature/FormBuilderTest.php

use Livewire\Livewire;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Http\Livewire\FormBuilder;

it('can create a new form', function () {
    Livewire::test(FormBuilder::class)
        ->set('formData.name', 'Contact Form')
        ->set('formData.slug', 'contact-form')
        ->call('save')
        ->assertDispatched('form-saved');

    expect(Form::where('slug', 'contact-form')->exists())->toBeTrue();
});

it('can add fields to a form', function () {
    $form = Form::factory()->create();

    Livewire::test(FormBuilder::class, ['form' => $form])
        ->call('addField', 'text')
        ->call('addField', 'email')
        ->assertCount('fields', 2);

    expect($form->fresh()->fields)->toHaveCount(2);
});

it('can reorder fields', function () {
    $form = Form::factory()->withFields(3)->create();
    $fields = $form->fields;

    Livewire::test(FormBuilder::class, ['form' => $form])
        ->call('reorderFields', [$fields[2]->id, $fields[0]->id, $fields[1]->id]);

    $reordered = $form->fresh()->fields;
    expect($reordered[0]->id)->toBe($fields[2]->id);
});

it('can duplicate a field', function () {
    $form = Form::factory()->withFields(1)->create();
    $field = $form->fields->first();

    Livewire::test(FormBuilder::class, ['form' => $form])
        ->call('duplicateField', $field->id);

    expect($form->fresh()->fields)->toHaveCount(2);
});

it('can enable multi-step mode', function () {
    $form = Form::factory()->withFields(3)->create();

    Livewire::test(FormBuilder::class, ['form' => $form])
        ->call('enableMultiStep');

    $form->refresh();
    expect($form->is_multi_step)->toBeTrue()
        ->and($form->steps)->toHaveCount(1)
        ->and($form->steps->first()->fields)->toHaveCount(3);
});
```

### Form Renderer Tests

```php
<?php

// tests/Feature/FormRendererTest.php

use Livewire\Livewire;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Http\Livewire\FormRenderer;

it('renders form fields', function () {
    $form = Form::factory()
        ->has(FormField::factory()->text()->state(['label' => 'Name']))
        ->has(FormField::factory()->email()->state(['label' => 'Email']))
        ->create();

    Livewire::test(FormRenderer::class, ['form' => $form])
        ->assertSee('Name')
        ->assertSee('Email');
});

it('validates required fields', function () {
    $form = Form::factory()
        ->has(FormField::factory()->email()->required())
        ->create();

    Livewire::test(FormRenderer::class, ['form' => $form])
        ->set('formData.email', '')
        ->call('submit')
        ->assertHasErrors('formData.email');
});

it('submits form successfully', function () {
    $form = Form::factory()
        ->has(FormField::factory()->text()->state(['name' => 'name']))
        ->has(FormField::factory()->email()->state(['name' => 'email']))
        ->create();

    Livewire::test(FormRenderer::class, ['form' => $form])
        ->set('formData.name', 'John Doe')
        ->set('formData.email', 'john@example.com')
        ->call('submit')
        ->assertSet('isSubmitted', true);

    expect($form->submissions)->toHaveCount(1);
});

it('silently ignores honeypot submissions', function () {
    $form = Form::factory()->withFields(1)->create();

    Livewire::test(FormRenderer::class, ['form' => $form])
        ->set('honeypot', 'bot-value')
        ->call('submit')
        ->assertSet('isSubmitted', true);

    expect($form->submissions)->toHaveCount(0);
});
```

### Conditional Logic Tests

```php
<?php

// tests/Feature/ConditionalLogicTest.php

use Livewire\Livewire;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Http\Livewire\FormRenderer;

it('shows field when condition is met', function () {
    $form = Form::factory()->create();

    $triggerField = FormField::factory()->create([
        'form_id' => $form->id,
        'type' => 'select',
        'name' => 'has_website',
        'field_config' => [
            'options' => [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'no', 'label' => 'No'],
            ],
        ],
    ]);

    $conditionalField = FormField::factory()->create([
        'form_id' => $form->id,
        'name' => 'website_url',
        'conditional_logic' => [
            'action' => 'show',
            'logic' => 'all',
            'rules' => [
                ['field_uuid' => $triggerField->uuid, 'operator' => 'equals', 'value' => 'yes'],
            ],
        ],
    ]);

    $component = Livewire::test(FormRenderer::class, ['form' => $form]);

    // Initially hidden
    $component->set('formData.has_website', 'no');
    expect($component->get('visibleFields')->pluck('name')->toArray())
        ->not->toContain('website_url');

    // Shown when condition met
    $component->set('formData.has_website', 'yes');
    expect($component->get('visibleFields')->pluck('name')->toArray())
        ->toContain('website_url');
});

it('does not validate hidden fields', function () {
    $form = Form::factory()->create();

    $triggerField = FormField::factory()->create([
        'form_id' => $form->id,
        'name' => 'show_details',
        'type' => 'checkbox',
    ]);

    $conditionalField = FormField::factory()->required()->create([
        'form_id' => $form->id,
        'name' => 'details',
        'conditional_logic' => [
            'action' => 'show',
            'logic' => 'all',
            'rules' => [
                ['field_uuid' => $triggerField->uuid, 'operator' => 'equals', 'value' => '1'],
            ],
        ],
    ]);

    // When hidden, required field should not cause validation error
    Livewire::test(FormRenderer::class, ['form' => $form])
        ->set('formData.show_details', '0')
        ->set('formData.details', '')
        ->call('submit')
        ->assertHasNoErrors('formData.details');
});
```

### Notification Tests

```php
<?php

// tests/Feature/NotificationTest.php

use Illuminate\Support\Facades\Mail;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\NotificationService;
use ArtisanPackUI\Forms\Mail\FormSubmissionNotification;

beforeEach(function () {
    Mail::fake();
});

it('sends admin notification on submission', function () {
    $form = Form::factory()->create();
    FormNotification::factory()->admin()->create([
        'form_id' => $form->id,
        'to_email' => 'admin@example.com',
    ]);

    $submission = FormSubmission::factory()->create(['form_id' => $form->id]);

    app(NotificationService::class)->sendNotifications($submission);

    Mail::assertSent(FormSubmissionNotification::class, function ($mail) {
        return $mail->hasTo('admin@example.com');
    });
});

it('sends autoresponder to email field value', function () {
    $form = Form::factory()->create();
    FormNotification::factory()->autoresponder()->create([
        'form_id' => $form->id,
        'to_field' => 'email',
    ]);

    $submission = FormSubmission::factory()
        ->withValues(['email' => 'user@example.com'])
        ->create(['form_id' => $form->id]);

    app(NotificationService::class)->sendNotifications($submission);

    Mail::assertSent(FormSubmissionNotification::class, function ($mail) {
        return $mail->hasTo('user@example.com');
    });
});

it('does not send notification when conditions not met', function () {
    $form = Form::factory()->create();
    FormNotification::factory()->create([
        'form_id' => $form->id,
        'conditional_logic' => [
            'logic' => 'all',
            'rules' => [
                ['field_name' => 'type', 'operator' => 'equals', 'value' => 'premium'],
            ],
        ],
    ]);

    $submission = FormSubmission::factory()
        ->withValues(['type' => 'standard'])
        ->create(['form_id' => $form->id]);

    app(NotificationService::class)->sendNotifications($submission);

    Mail::assertNotSent(FormSubmissionNotification::class);
});
```

---

## Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/FormBuilderTest.php

# Run tests with filter
./vendor/bin/pest --filter="can create a new form"

# Run tests with coverage
./vendor/bin/pest --coverage
```

---

## Related Documents

- [02-models-and-relationships.md](02-models-and-relationships.md) - Models to test
- [04-form-renderer.md](04-form-renderer.md) - Renderer to test
- [06-conditional-logic.md](06-conditional-logic.md) - Conditions to test
