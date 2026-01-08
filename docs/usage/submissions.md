---
title: Submissions
---

# Submissions

Form submissions are stored in the database and can be viewed, searched, exported, and processed.

## Viewing Submissions

### Admin Interface

Access submissions at:

- `/admin/forms/submissions` - All submissions across all forms
- `/admin/forms/{form}/submissions` - Submissions for a specific form
- `/admin/forms/{form}/submissions/{submission}` - Single submission detail

### Programmatically

```php
use ArtisanPackUI\Forms\Models\FormSubmission;

// Get all submissions for a form
$submissions = FormSubmission::where('form_id', $formId)
    ->with('values', 'uploads')
    ->latest()
    ->paginate(20);

// Get a single submission
$submission = FormSubmission::with('values', 'uploads')->find($submissionId);

// Access field values
foreach ($submission->values as $value) {
    echo $value->field_name . ': ' . $value->value;
}
```

## Submission Data Structure

Each submission contains:

| Column | Description |
|--------|-------------|
| `id` | Unique identifier |
| `form_id` | Related form ID |
| `submission_number` | Human-readable number |
| `ip_address` | Submitter's IP (if enabled) |
| `user_agent` | Browser user agent (if enabled) |
| `metadata` | JSON metadata |
| `created_at` | Submission timestamp |

### Accessing Values

```php
$submission = FormSubmission::with('values')->find($id);

// Get all values as array
$data = $submission->getFormData();
// ['name' => 'John', 'email' => 'john@example.com']

// Get specific value
$email = $submission->getValue('email');

// Get value with label
$values = $submission->getValuesWithLabels();
// [['label' => 'Email', 'name' => 'email', 'value' => 'john@example.com']]
```

## Searching Submissions

### Via Admin Interface

Use the search box to filter by:

- Field values
- Submission number
- Date range
- IP address

### Programmatically

```php
use ArtisanPackUI\Forms\Models\FormSubmission;

// Search by value
$submissions = FormSubmission::whereHas('values', function ($query) {
    $query->where('value', 'like', '%john%');
})->get();

// Filter by date range
$submissions = FormSubmission::whereBetween('created_at', [
    now()->subDays(7),
    now(),
])->get();

// Filter by form and status
$submissions = FormSubmission::where('form_id', $formId)
    ->where('created_at', '>=', now()->subMonth())
    ->orderBy('created_at', 'desc')
    ->get();
```

## Exporting Submissions

### Via Admin Interface

1. Go to the form's submissions page
2. Click "Export"
3. Choose format (CSV, Excel)
4. Download the file

### Programmatically

```php
use ArtisanPackUI\Forms\Services\ExportService;

$exportService = app(ExportService::class);

// Export to CSV
$csvContent = $exportService->toCsv($form);

// Export to array
$data = $exportService->toArray($form);

// Export with date range
$data = $exportService->toArray($form, [
    'start_date' => now()->subMonth(),
    'end_date' => now(),
]);
```

## Processing Submissions

### Event Listeners

```php
use ArtisanPackUI\Forms\Events\FormSubmitted;

class ProcessSubmission
{
    public function handle(FormSubmitted $event): void
    {
        $form = $event->form;
        $submission = $event->submission;

        // Add to CRM
        $this->crm->createContact([
            'email' => $submission->getValue('email'),
            'name' => $submission->getValue('name'),
        ]);

        // Send to analytics
        $this->analytics->track('form_submission', [
            'form_id' => $form->id,
            'form_name' => $form->name,
        ]);
    }
}
```

### Queue Processing

For heavy processing, queue the work:

```php
class ProcessSubmission
{
    public function handle(FormSubmitted $event): void
    {
        ProcessSubmissionJob::dispatch($event->submission);
    }
}
```

## Deleting Submissions

### Single Submission

```php
$submission = FormSubmission::find($id);
$submission->delete(); // Also deletes values and uploads
```

### Bulk Delete

```php
// Delete old submissions
FormSubmission::where('created_at', '<', now()->subYear())->delete();

// Or use the prune command
php artisan forms:prune-submissions
```

### Automatic Pruning

Configure retention in `config/artisanpack/forms.php`:

```php
'submissions' => [
    'retention_days' => 365, // Delete after 1 year
],
```

Schedule the prune command:

```php
// app/Console/Kernel.php or routes/console.php
Schedule::command('forms:prune-submissions')->daily();
```

## Submission Numbers

Submissions are assigned sequential numbers based on the configured format:

```php
'submissions' => [
    'submission_number_format' => 'FORM-{year}-{sequence}',
    // Results in: FORM-2024-0001, FORM-2024-0002, etc.
],
```

Available placeholders:

- `{year}` - Current year
- `{month}` - Current month (2 digits)
- `{day}` - Current day (2 digits)
- `{sequence}` - Sequential number
- `{form_id}` - Form ID

## Metadata

Store additional metadata with submissions:

```php
use ArtisanPackUI\Forms\Services\SubmissionService;

$submissionService = app(SubmissionService::class);

$submission = $submissionService->create($form, $formData, [
    'source' => 'landing-page',
    'utm_source' => request('utm_source'),
    'utm_campaign' => request('utm_campaign'),
]);

// Access metadata
$source = $submission->getMetadata('source');
$allMeta = $submission->metadata; // JSON decoded array
```

## Next Steps

- [Notifications](./notifications.md) - Email notifications for submissions
- [File Uploads](./file-uploads.md) - Handling uploaded files
- [API Reference](../api/api.md) - Detailed model and service documentation
