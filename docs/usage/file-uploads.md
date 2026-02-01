---
title: File Uploads
---

# File Uploads

Forms can include file upload fields for collecting documents, images, and other files from users.

## Adding File Upload Fields

### Via Form Builder

1. Add a "File" field to your form
2. Configure accepted file types
3. Set maximum file size
4. Optionally allow multiple files

### Programmatically

```php
use ArtisanPackUI\Forms\Models\FormField;

FormField::create([
    'form_id' => $form->id,
    'type' => 'file',
    'name' => 'resume',
    'label' => 'Upload Resume',
    'required' => false,
    'settings' => [
        'accept' => '.pdf,.doc,.docx',
        'max_size' => 5120, // KB (5MB)
        'multiple' => false,
        'max_files' => 1,
    ],
    'order' => 5,
]);
```

## Configuration

### Global Settings

```php
// config/artisanpack/forms.php
'uploads' => [
    // Storage disk
    'disk' => 'form-uploads',

    // Directory within disk
    'directory' => 'uploads',

    // Maximum file size in KB (default 10MB)
    'max_size' => 10240,

    // Allowed MIME types
    'allowed_mimes' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
    ],
],
```

### Per-Field Settings

| Setting | Description |
|---------|-------------|
| `accept` | Accepted file extensions (e.g., `.pdf,.doc`) |
| `max_size` | Maximum size in KB |
| `multiple` | Allow multiple files |
| `max_files` | Maximum number of files (if multiple) |

## Storage

### Default Storage

Files are stored privately by default in `storage/app/form-uploads/`.

### Custom Disk

Configure a custom disk in `config/filesystems.php`:

```php
'disks' => [
    'form-uploads' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'visibility' => 'private',
    ],
],
```

Then update the forms config:

```php
'uploads' => [
    'disk' => 'form-uploads',
],
```

## Accessing Uploads

### From Submission

```php
$submission = FormSubmission::with('uploads')->find($id);

foreach ($submission->uploads as $upload) {
    echo $upload->original_name; // Original filename
    echo $upload->path;          // Storage path
    echo $upload->mime_type;     // MIME type
    echo $upload->size;          // Size in bytes
}
```

### Download URL

```php
// Generate temporary download URL
$url = $upload->getDownloadUrl();

// Or use the download route
$url = route('forms.uploads.download', [
    'form' => $form,
    'submission' => $submission,
    'upload' => $upload,
]);
```

### Reading File Content

```php
use Illuminate\Support\Facades\Storage;

$disk = Storage::disk($upload->disk);
$content = $disk->get($upload->path);
```

## Security

### Authorization

File downloads are protected by the `SubmissionPolicy`:

```php
// Users can only download uploads from submissions they have access to
public function downloadUpload(User $user, FormSubmission $submission): bool
{
    return $this->view($user, $submission);
}
```

### Validation

Files are validated on upload:

- MIME type checking
- File size validation
- Extension validation

### Sanitization

Filenames are sanitized to prevent path traversal and other attacks:

```php
// Original: "../../../etc/passwd"
// Stored as: "a1b2c3d4_passwd"
```

## Deleting Uploads

### With Submission

When a submission is deleted, associated uploads are automatically removed:

```php
$submission->delete(); // Deletes files from storage
```

### Individual Upload

```php
use Illuminate\Support\Facades\Storage;

// Delete file from storage
Storage::disk($upload->disk)->delete($upload->path);

// Delete database record
$upload->delete();
```

## File Upload Events

Listen for upload events:

```php
use ArtisanPackUI\Forms\Events\FileUploaded;

class ProcessUpload
{
    public function handle(FileUploaded $event): void
    {
        $upload = $event->upload;

        // Scan for viruses
        $this->virusScanner->scan($upload->path);

        // Process image
        if (str_starts_with($upload->mime_type, 'image/')) {
            $this->imageProcessor->process($upload);
        }
    }
}
```

## Displaying Uploads

### In Admin

Uploads are displayed in the submission detail view with download links.

### In Custom Views

```blade
@foreach ($submission->uploads as $upload)
    <div class="upload">
        <span class="filename">{{ $upload->original_name }}</span>
        <span class="size">{{ $upload->humanFileSize() }}</span>
        <a href="{{ $upload->getDownloadUrl() }}" download>
            Download
        </a>
    </div>
@endforeach
```

### Preview Images

```blade
@if ($upload->isImage())
    <img src="{{ $upload->getPreviewUrl() }}" alt="{{ $upload->original_name }}">
@endif
```

## Pruning Old Uploads

The prune command also deletes associated files:

```bash
php artisan forms:prune-submissions --days=365
```

This deletes both database records and physical files.

## Troubleshooting

### Upload Fails

Check:

1. File size within limits (`post_max_size`, `upload_max_filesize` in PHP)
2. MIME type is allowed
3. Storage directory is writable
4. Disk space available

### Download Fails

Check:

1. File exists on disk
2. User has permission to access submission
3. Storage disk is properly configured

### Files Not Deleted

Ensure the storage disk matches the upload's disk:

```php
$upload->disk; // Should match configured disk
```

## Next Steps

- [Multi-Step Forms](Multi-Step-Forms) - Create multi-step forms
- [Notifications](Notifications) - Include uploads in notifications
- [Submissions](Submissions) - Managing submission data
