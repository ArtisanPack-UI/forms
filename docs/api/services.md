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

#### create

Create a new step. When `title` is omitted, it defaults to "Step N".

```php
public function create(Form $form, array $data = []): FormStep
```

#### update

Update a step.

```php
public function update(FormStep $step, array $data): FormStep
```

#### delete

Delete a step. Its fields move to the previous step, or to no step if it was the first.

```php
public function delete(FormStep $step): bool
```

#### reorder

Reorder a form's steps from an ordered array of step IDs.

```php
public function reorder(Form $form, array $orderedIds): void
```

#### getById / getSteps / getFirstStep / getLastStep

Look up a form's steps.

```php
public function getById(Form $form, int $stepId): ?FormStep
public function getSteps(Form $form): Collection
public function getFirstStep(Form $form): ?FormStep
public function getLastStep(Form $form): ?FormStep
```

#### convertToMultiStep / convertToSingleStep

Switch an existing form between single-step and multi-step. `convertToMultiStep()` creates a first step and moves every field into it. `convertToSingleStep()` detaches every field from its step and deletes the steps. Neither method changes `is_multi_step`, so update the flag in the same operation:

```php
public function convertToMultiStep(Form $form): FormStep
public function convertToSingleStep(Form $form): void

$stepService->convertToMultiStep($form);
$form->update(['is_multi_step' => true]);
```

> **Note:** Changing `is_multi_step` on a form that already has fields without converting it leaves the step data inconsistent, and the renderer can then show no fields. The admin form builder's **Multi-Step Form** toggle does both for you.

#### initializeMultiStep

Create a default first step only if the form has no steps yet.

```php
public function initializeMultiStep(Form $form): ?FormStep
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

#### shouldSendNotifications

*(since 1.5.0)*

Decide whether the notification pipeline should run for a submission. Quarantined submissions are suppressed by default; the decision passes through the `ap.forms.submission.should_send_notifications` filter so it can be overridden per submission.

```php
public function shouldSendNotifications(FormSubmission $submission): bool
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

- [Events](Events) - Event documentation
- [Jobs](Jobs) - Queue job documentation
- [Models](Models) - Model documentation
