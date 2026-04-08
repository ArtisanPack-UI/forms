---
title: API Overview
---

# API Overview

This section provides technical documentation for the ArtisanPack UI Forms package classes and interfaces.

## Namespace Structure

```
ArtisanPackUI\Forms\
├── Config\                  # Configuration classes
│   ├── FieldTypes.php
│   └── ConditionalLogic.php
├── Console\Commands\        # Artisan commands
│   ├── InstallFrontend.php
│   └── PruneFormSubmissions.php
├── Events\                  # Event classes
│   ├── FormCreated.php
│   ├── FormUpdated.php
│   ├── FormDeleted.php
│   ├── FormSubmitted.php
│   ├── SubmissionUpdated.php
│   └── SubmissionDeleted.php
├── Facades\                 # Laravel facades
│   └── Forms.php
├── Http\
│   ├── Controllers\         # HTTP controllers
│   │   ├── Api\             # REST API controllers
│   │   │   ├── FormApiController.php
│   │   │   ├── FormFieldApiController.php
│   │   │   ├── FormStepApiController.php
│   │   │   ├── NotificationApiController.php
│   │   │   └── SubmissionApiController.php
│   │   ├── FormController.php
│   │   └── SubmissionController.php
│   ├── Requests\            # Form requests
│   │   ├── Api\             # API form requests
│   │   │   ├── BulkSubmissionApiRequest.php
│   │   │   ├── ReorderFieldsApiRequest.php
│   │   │   ├── ReorderStepsApiRequest.php
│   │   │   ├── StoreFieldApiRequest.php
│   │   │   ├── StoreFormApiRequest.php
│   │   │   ├── StoreNotificationApiRequest.php
│   │   │   ├── StoreStepApiRequest.php
│   │   │   ├── SubmitFormApiRequest.php
│   │   │   ├── UpdateFieldApiRequest.php
│   │   │   ├── UpdateFormApiRequest.php
│   │   │   ├── UpdateNotificationApiRequest.php
│   │   │   ├── UpdateStepApiRequest.php
│   │   │   └── UpdateSubmissionApiRequest.php
│   │   ├── StoreFormRequest.php
│   │   └── UpdateFormRequest.php
│   └── Resources\           # API resources
│       ├── FormFieldResource.php
│       ├── FormNotificationResource.php
│       ├── FormRenderResource.php
│       ├── FormResource.php
│       ├── FormStepResource.php
│       ├── FormSubmissionResource.php
│       ├── FormSubmissionValueResource.php
│       ├── FormUploadResource.php
│       └── PaginatedResourceCollection.php
├── Jobs\                    # Queue jobs
│   ├── SendFormNotification.php
│   └── SendWebhook.php
├── Listeners\               # Event listeners
│   └── SendWebhookOnSubmission.php
├── Livewire\                # Livewire components
│   ├── FormBuilder.php
│   ├── FormRenderer.php
│   ├── FormsList.php
│   ├── NotificationEditor.php
│   ├── SubmissionDetail.php
│   └── SubmissionsList.php
├── Mail\                    # Mailable classes
│   └── FormSubmissionNotification.php
├── Models\                  # Eloquent models
│   ├── Form.php
│   ├── FormField.php
│   ├── FormNotification.php
│   ├── FormStep.php
│   ├── FormSubmission.php
│   ├── FormSubmissionValue.php
│   └── FormUpload.php
├── Policies\                # Authorization policies
│   ├── FormPolicy.php
│   └── SubmissionPolicy.php
├── Services\                # Business logic services
│   ├── ConditionalLogicService.php
│   ├── ExportService.php
│   ├── FieldService.php
│   ├── FormService.php
│   ├── IntegrationService.php
│   ├── NotificationService.php
│   ├── StepService.php
│   └── SubmissionService.php
├── Forms.php                # Main package class
├── FormsServiceProvider.php # Service provider
└── helpers.php              # Global helper functions
```

## Using the Facade

```php
use ArtisanPackUI\Forms\Facades\Forms;

// Get a form by slug
$form = Forms::getBySlug('contact');

// Create a submission
$submission = Forms::submit($form, $data);
```

## Dependency Injection

Inject services via constructor or method injection:

```php
use ArtisanPackUI\Forms\Services\FormService;
use ArtisanPackUI\Forms\Services\SubmissionService;

class MyController extends Controller
{
    public function __construct(
        private FormService $formService,
        private SubmissionService $submissionService,
    ) {}

    public function store(Request $request, string $slug)
    {
        $form = $this->formService->getBySlug($slug);
        $submission = $this->submissionService->create($form, $request->all());

        return redirect()->back()->with('success', 'Form submitted!');
    }
}
```

## Helper Functions

Global helper functions are available:

```php
// Get accessibility helpers (from artisanpack-ui/accessibility)
$contrast = a11yGetContrastColor('#3b82f6');

// Get toast duration setting
$duration = getToastDuration();
```

## Configuration Access

```php
// Access configuration values
$prefix = config('artisanpack.forms.admin.prefix');
$maxSize = config('artisanpack.forms.uploads.max_size');

// Check if feature is enabled
$spamProtection = config('artisanpack.forms.spam_protection.honeypot.enabled');
```

## Filter Hooks

Extend functionality using filter hooks:

```php
use function addFilter;

// Modify validation rules
addFilter('forms.validation_rules', function ($rules, $form) {
    // Add custom rules
    return $rules;
});

// Modify notification message
addFilter('forms.notification_message', function ($message, $notification, $submission) {
    return $message;
});

// Modify webhook payload
addFilter('forms.webhook_payload', function ($payload, $form, $submission) {
    $payload['custom'] = 'data';
    return $payload;
});
```

## Action Hooks

Execute code at specific points:

```php
use function doAction;
use function addAction;

// Listen for form events
addAction('forms.submitted', function ($form, $submission) {
    // Custom processing
});

// Trigger custom actions
doAction('forms.submitted', $form, $submission);
```

## Next Steps

- [Models](Models) - Eloquent model documentation
- [Services](Services) - Service class documentation
- [Events](Events) - Event documentation
