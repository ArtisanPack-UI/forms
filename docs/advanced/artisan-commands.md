---
title: Artisan Commands
---

# Artisan Commands

Command-line tools for managing forms.

## Available Commands

| Command | Description |
|---------|-------------|
| `forms:prune-submissions` | Delete old form submissions |

## forms:prune-submissions

Deletes form submissions older than the configured retention period, including associated uploaded files.

### Usage

```bash
php artisan forms:prune-submissions
```

### Options

| Option | Description |
|--------|-------------|
| `--days=N` | Override retention days from config |
| `--dry-run` | Show what would be deleted without deleting |

### Examples

```bash
# Use configured retention days
php artisan forms:prune-submissions

# Delete submissions older than 90 days
php artisan forms:prune-submissions --days=90

# Preview what would be deleted
php artisan forms:prune-submissions --days=30 --dry-run

# Force delete all submissions older than 1 year
php artisan forms:prune-submissions --days=365
```

### Output

```
Deleted 150 submission(s) older than 90 days.
Deleted 47 associated file(s) from storage.
```

With `--dry-run`:

```
[Dry Run] Would delete 150 submission(s) older than 90 days.
[Dry Run] Would delete 47 associated file(s):
  - [form-uploads] uploads/abc123_resume.pdf (resume.pdf)
  - [form-uploads] uploads/def456_photo.jpg (profile-photo.jpg)
  ...
```

### Configuration

Set default retention in config:

```php
// config/artisanpack/forms.php
'submissions' => [
    'retention_days' => 365, // Delete after 1 year
    // Set to null to keep forever
],
```

### Scheduling

Schedule the command to run automatically:

```php
// routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::command('forms:prune-submissions')->daily();

// Or with custom retention
Schedule::command('forms:prune-submissions --days=90')->daily();
```

For Laravel 10:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('forms:prune-submissions')->daily();
}
```

### What Gets Deleted

1. **Submissions** older than retention period
2. **Submission values** (field data) - cascade deleted
3. **Upload records** - cascade deleted
4. **Physical files** - deleted from storage disk

### Safety Features

- Uses database transactions (rollback on failure)
- Logs all deleted files
- Warns about files that couldn't be deleted
- Dry-run option to preview changes

### Error Handling

If file deletion fails:

```
Deleted 150 submission(s) older than 90 days.
Deleted 45 associated file(s) from storage.
Warning: Failed to delete 2 file(s). Check logs for details.
```

Check Laravel logs for details:

```
[error] Failed to delete form upload file during prune {
    "upload_id": 123,
    "disk": "form-uploads",
    "path": "uploads/abc123_file.pdf",
    "error": "File not found"
}
```

## Creating Custom Commands

### Example: Export Command

```php
// app/Console/Commands/ExportFormSubmissions.php
namespace App\Console\Commands;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Services\ExportService;
use Illuminate\Console\Command;

class ExportFormSubmissions extends Command
{
    protected $signature = 'forms:export
                            {slug : Form slug to export}
                            {--format=csv : Export format (csv, json)}
                            {--output= : Output file path}';

    protected $description = 'Export form submissions';

    public function handle(ExportService $exportService): int
    {
        $form = Form::where('slug', $this->argument('slug'))->first();

        if (!$form) {
            $this->error('Form not found.');
            return self::FAILURE;
        }

        $format = $this->option('format');
        $output = $this->option('output') ?? "submissions-{$form->slug}.{$format}";

        $data = match ($format) {
            'csv' => $exportService->toCsv($form),
            'json' => json_encode($exportService->toArray($form), JSON_PRETTY_PRINT),
            default => $exportService->toCsv($form),
        };

        file_put_contents($output, $data);

        $this->info("Exported to: {$output}");

        return self::SUCCESS;
    }
}
```

### Example: Stats Command

```php
// app/Console/Commands/FormStats.php
namespace App\Console\Commands;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Console\Command;

class FormStats extends Command
{
    protected $signature = 'forms:stats {--form= : Specific form slug}';

    protected $description = 'Display form statistics';

    public function handle(): int
    {
        $query = Form::withCount('submissions');

        if ($slug = $this->option('form')) {
            $query->where('slug', $slug);
        }

        $forms = $query->get();

        $this->table(
            ['ID', 'Name', 'Slug', 'Active', 'Submissions'],
            $forms->map(fn ($form) => [
                $form->id,
                $form->name,
                $form->slug,
                $form->is_active ? 'Yes' : 'No',
                $form->submissions_count,
            ])
        );

        $this->newLine();
        $this->info('Total submissions: ' . FormSubmission::count());
        $this->info('Today: ' . FormSubmission::whereDate('created_at', today())->count());
        $this->info('This week: ' . FormSubmission::where('created_at', '>=', now()->subWeek())->count());

        return self::SUCCESS;
    }
}
```

## Command Registration

Commands in `app/Console/Commands/` are auto-registered in Laravel 11+.

For Laravel 10, register in `Kernel.php`:

```php
protected $commands = [
    \App\Console\Commands\ExportFormSubmissions::class,
    \App\Console\Commands\FormStats::class,
];
```

## Next Steps

- [Configuration](../installation/configuration.md) - Configuration options
- [Submissions](../usage/submissions.md) - Managing submissions
- [Advanced Overview](./advanced.md) - Advanced features
