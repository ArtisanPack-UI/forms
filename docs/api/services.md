---
title: Services
---

# Services

Service class documentation for ArtisanPack UI Forms.

## FormService

Handles form CRUD operations and retrieval.

### Methods

```php
use ArtisanPackUI\Forms\Services\FormService;

$formService = app(FormService::class);
```

#### getBySlug

Retrieve a form by its slug.

```php
public function getBySlug(string $slug): ?Form
```

**Example:**

```php
$form = $formService->getBySlug('contact');
```

#### getById

Retrieve a form by ID.

```php
public function getById(int $id): ?Form
```

#### create

Create a new form.

```php
public function create(array $data): Form
```

**Example:**

```php
$form = $formService->create([
    'name' => 'Contact Form',
    'slug' => 'contact',
    'description' => 'Contact us form',
    'is_active' => true,
]);
```

#### update

Update an existing form.

```php
public function update(Form $form, array $data): Form
```

#### delete

Delete a form and its related data.

```php
public function delete(Form $form): bool
```

#### duplicate

Duplicate a form with all fields and settings.

```php
public function duplicate(Form $form, ?string $newSlug = null): Form
```

**Example:**

```php
$newForm = $formService->duplicate($form, 'contact-v2');
```

#### getActiveForms

Get all active forms.

```php
public function getActiveForms(): Collection
```

---

## FieldService

Manages form fields.

### Methods

```php
use ArtisanPackUI\Forms\Services\FieldService;

$fieldService = app(FieldService::class);
```

#### createField

Create a new field for a form.

```php
public function createField(Form $form, array $data): FormField
```

**Example:**

```php
$field = $fieldService->createField($form, [
    'type' => 'text',
    'name' => 'full_name',
    'label' => 'Full Name',
    'required' => true,
    'order' => 1,
]);
```

#### updateField

Update an existing field.

```php
public function updateField(FormField $field, array $data): FormField
```

#### deleteField

Delete a field.

```php
public function deleteField(FormField $field): bool
```

#### reorderFields

Reorder fields for a form.

```php
public function reorderFields(Form $form, array $fieldIds): void
```

**Example:**

```php
$fieldService->reorderFields($form, [3, 1, 2, 5, 4]);
```

#### getFieldTypes

Get available field types.

```php
public function getFieldTypes(): array
```

---

## StepService

Manages multi-step form steps.

### Methods

```php
use ArtisanPackUI\Forms\Services\StepService;

$stepService = app(StepService::class);
```

#### createStep

Create a new step.

```php
public function createStep(Form $form, array $data): FormStep
```

#### updateStep

Update a step.

```php
public function updateStep(FormStep $step, array $data): FormStep
```

#### deleteStep

Delete a step and reassign its fields.

```php
public function deleteStep(FormStep $step): bool
```

#### reorderSteps

Reorder steps.

```php
public function reorderSteps(Form $form, array $stepIds): void
```

---

## SubmissionService

Handles form submissions.

### Methods

```php
use ArtisanPackUI\Forms\Services\SubmissionService;

$submissionService = app(SubmissionService::class);
```

#### create

Create a new submission.

```php
public function create(
    Form $form,
    array $data,
    array $metadata = []
): FormSubmission
```

**Example:**

```php
$submission = $submissionService->create($form, [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'message' => 'Hello!',
], [
    'source' => 'landing-page',
    'utm_campaign' => 'summer2024',
]);
```

#### processFiles

Process uploaded files for a submission.

```php
public function processFiles(
    FormSubmission $submission,
    array $files
): Collection
```

#### delete

Delete a submission and its files.

```php
public function delete(FormSubmission $submission): bool
```

#### getForForm

Get submissions for a form with pagination.

```php
public function getForForm(
    Form $form,
    int $perPage = 15,
    array $filters = []
): LengthAwarePaginator
```

**Example:**

```php
$submissions = $submissionService->getForForm($form, 20, [
    'search' => 'john',
    'start_date' => '2024-01-01',
    'end_date' => '2024-12-31',
]);
```

---

## NotificationService

Handles email notifications.

### Methods

```php
use ArtisanPackUI\Forms\Services\NotificationService;

$notificationService = app(NotificationService::class);
```

#### sendNotifications

Send all active notifications for a submission.

```php
public function sendNotifications(FormSubmission $submission): void
```

#### parseTemplate

Parse placeholders in a template.

```php
public function parseTemplate(
    string $template,
    FormSubmission $submission
): string
```

**Example:**

```php
$parsed = $notificationService->parseTemplate(
    'Hello {name}, thank you for contacting us!',
    $submission
);
```

#### formatAllFieldsAsTable

Format submission data as HTML table.

```php
public function formatAllFieldsAsTable(
    FormSubmission $submission
): string
```

#### getCcEmails

Get CC email addresses.

```php
public function getCcEmails(FormNotification $notification): array
```

#### getBccEmails

Get BCC email addresses.

```php
public function getBccEmails(FormNotification $notification): array
```

---

## ConditionalLogicService

Evaluates conditional logic rules.

### Methods

```php
use ArtisanPackUI\Forms\Services\ConditionalLogicService;

$conditionService = app(ConditionalLogicService::class);
```

#### evaluateConditions

Evaluate conditions against form data.

```php
public function evaluateConditions(
    ?array $conditions,
    array $formData
): bool
```

**Example:**

```php
$isVisible = $conditionService->evaluateConditions([
    'logic' => 'and',
    'rules' => [
        ['field' => 'country', 'operator' => 'equals', 'value' => 'US'],
    ],
], $formData);
```

#### getVisibleFields

Get fields visible for current form state.

```php
public function getVisibleFields(Form $form, array $formData): Collection
```

#### isFieldVisible

Check if a specific field is visible.

```php
public function isFieldVisible(FormField $field, array $formData): bool
```

---

## ExportService

Handles data export operations.

### Methods

```php
use ArtisanPackUI\Forms\Services\ExportService;

$exportService = app(ExportService::class);
```

#### toCsv

Export submissions to CSV.

```php
public function toCsv(
    Form $form,
    array $options = []
): string
```

**Example:**

```php
$csv = $exportService->toCsv($form, [
    'start_date' => now()->subMonth(),
    'end_date' => now(),
]);
```

#### toArray

Export submissions to array.

```php
public function toArray(
    Form $form,
    array $options = []
): array
```

---

## IntegrationService

Manages third-party integrations.

### Methods

```php
use ArtisanPackUI\Forms\Services\IntegrationService;

$integrationService = app(IntegrationService::class);
```

#### getAvailableIntegrations

Get registered integrations.

```php
public function getAvailableIntegrations(): array
```

#### processSubmission

Process submission through integrations.

```php
public function processSubmission(FormSubmission $submission): void
```

## Next Steps

- [Events](./events.md) - Event documentation
- [Jobs](./jobs.md) - Queue job documentation
- [Models](./models.md) - Model documentation
