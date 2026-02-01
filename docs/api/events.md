---
title: Events
---

# Events

Event classes for ArtisanPack UI Forms.

## Overview

Events are dispatched at key points in the form lifecycle, allowing you to hook into form and submission operations.

## Form Events

### FormCreated

Dispatched when a new form is created.

```php
use ArtisanPackUI\Forms\Events\FormCreated;

// Event properties
$event->form;  // Form model instance

// Listening
use Illuminate\Support\Facades\Event;

Event::listen(FormCreated::class, function (FormCreated $event) {
    Log::info("Form created: {$event->form->name}");
});
```

### FormUpdated

Dispatched when a form is updated.

```php
use ArtisanPackUI\Forms\Events\FormUpdated;

// Event properties
$event->form;  // Form model instance

// Listening
Event::listen(FormUpdated::class, function (FormUpdated $event) {
    // Bust cache, notify admin, etc.
    Cache::forget("form_{$event->form->slug}");
});
```

### FormDeleted

Dispatched when a form is deleted.

```php
use ArtisanPackUI\Forms\Events\FormDeleted;

// Event properties
$event->form;  // Form model instance (soft deleted or being deleted)

// Listening
Event::listen(FormDeleted::class, function (FormDeleted $event) {
    // Clean up related data
    Log::warning("Form deleted: {$event->form->name}");
});
```

## Submission Events

### FormSubmitted

Dispatched when a form is successfully submitted.

```php
use ArtisanPackUI\Forms\Events\FormSubmitted;

// Event properties
$event->form;        // Form model instance
$event->submission;  // FormSubmission model instance

// Listening
Event::listen(FormSubmitted::class, function (FormSubmitted $event) {
    // Send to CRM
    CrmService::createLead([
        'email' => $event->submission->getValue('email'),
        'name' => $event->submission->getValue('name'),
    ]);

    // Track analytics
    Analytics::track('form_submission', [
        'form_id' => $event->form->id,
        'form_name' => $event->form->name,
    ]);
});
```

### SubmissionUpdated

Dispatched when a submission is updated.

```php
use ArtisanPackUI\Forms\Events\SubmissionUpdated;

// Event properties
$event->submission;  // FormSubmission model instance

// Listening
Event::listen(SubmissionUpdated::class, function (SubmissionUpdated $event) {
    // Log changes
    Log::info("Submission updated: {$event->submission->submission_number}");
});
```

### SubmissionDeleted

Dispatched when a submission is deleted.

```php
use ArtisanPackUI\Forms\Events\SubmissionDeleted;

// Event properties
$event->submission;  // FormSubmission model instance

// Listening
Event::listen(SubmissionDeleted::class, function (SubmissionDeleted $event) {
    // Clean up external systems
    CrmService::deleteLeadBySubmission($event->submission->id);
});
```

## Registering Listeners

### In EventServiceProvider

```php
// app/Providers/EventServiceProvider.php

use ArtisanPackUI\Forms\Events\FormSubmitted;
use App\Listeners\ProcessFormSubmission;

protected $listen = [
    FormSubmitted::class => [
        ProcessFormSubmission::class,
    ],
];
```

### Creating a Listener

```php
// app/Listeners/ProcessFormSubmission.php

namespace App\Listeners;

use ArtisanPackUI\Forms\Events\FormSubmitted;

class ProcessFormSubmission
{
    public function handle(FormSubmitted $event): void
    {
        $form = $event->form;
        $submission = $event->submission;

        // Process the submission
        if ($form->slug === 'contact') {
            $this->notifySlack($submission);
        }

        if ($form->slug === 'newsletter') {
            $this->addToMailingList($submission);
        }
    }

    protected function notifySlack($submission): void
    {
        // Send Slack notification
    }

    protected function addToMailingList($submission): void
    {
        // Add to mailing list
    }
}
```

### Using Closures

```php
// In a service provider boot() method

use ArtisanPackUI\Forms\Events\FormSubmitted;
use Illuminate\Support\Facades\Event;

Event::listen(FormSubmitted::class, function ($event) {
    // Quick inline handling
    Log::info('Form submitted', [
        'form' => $event->form->name,
        'submission' => $event->submission->id,
    ]);
});
```

## Queued Listeners

For time-consuming operations, implement `ShouldQueue`:

```php
namespace App\Listeners;

use ArtisanPackUI\Forms\Events\FormSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendToCrm implements ShouldQueue
{
    public $queue = 'integrations';

    public function handle(FormSubmitted $event): void
    {
        // Heavy processing runs in background
        CrmService::sync($event->submission);
    }
}
```

## Event Subscribers

Group related listeners:

```php
namespace App\Listeners;

use ArtisanPackUI\Forms\Events\FormCreated;
use ArtisanPackUI\Forms\Events\FormSubmitted;
use Illuminate\Events\Dispatcher;

class FormEventSubscriber
{
    public function handleFormCreated(FormCreated $event): void
    {
        // Handle form creation
    }

    public function handleFormSubmitted(FormSubmitted $event): void
    {
        // Handle form submission
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            FormCreated::class,
            [self::class, 'handleFormCreated']
        );

        $events->listen(
            FormSubmitted::class,
            [self::class, 'handleFormSubmitted']
        );
    }
}
```

Register in `EventServiceProvider`:

```php
protected $subscribe = [
    \App\Listeners\FormEventSubscriber::class,
];
```

## Testing Events

```php
use ArtisanPackUI\Forms\Events\FormSubmitted;
use Illuminate\Support\Facades\Event;

test('form submission dispatches event', function () {
    Event::fake([FormSubmitted::class]);

    // Submit form...
    $this->post('/forms/contact', $data);

    Event::assertDispatched(FormSubmitted::class, function ($event) {
        return $event->form->slug === 'contact';
    });
});
```

## Next Steps

- [Jobs](Jobs) - Queue job documentation
- [Services](Services) - Service documentation
- [Webhooks](Advanced-Webhooks) - Webhook configuration
