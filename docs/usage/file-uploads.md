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
| `max_total_size` | Maximum size of all files together in KB (if multiple) |
| `min_width`, `max_width`, `min_height`, `max_height` | Pixel bounds for uploaded images; other files are not checked |

A multi-file field stores one `form_uploads` row per file and one submission value that lists them all: `upload_id` is the first upload and `value_array` holds every upload ID in order. Use `$value->getUploads()` to get them.

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

### Media Library Storage

A file field's **Storage** setting (Advanced tab, `field_config.storage`) can save its uploads with `artisanpack-ui/media-library` instead (since 1.6.0). Each file becomes a media item titled with the file's name, and its `form_uploads` row records the item in `media_id` with the item's `disk` and `path`, so downloads and URLs keep working. Deleting the upload or its submission, including through `forms:prune-submissions`, deletes the media item and its files too.

```php
'uploads' => [
    'media_library' => [
        'folder_id'   => env( 'FORMS_MEDIA_FOLDER_ID' ),   // a media folder for form uploads
        'uploaded_by' => env( 'FORMS_MEDIA_UPLOADED_BY' ), // the user media items are attributed to
    ],
],
```

The media library's disk is public by default, so files stored there can be reached by URL. Use this storage only for files that may be public, or configure the media library with a private disk. To keep that safe:

- The file is handed to the media library under the extension found from its contents, so `invoice.php` holding a PNG is saved as `invoice-….png`. The visitor's file name is kept as the media item's title and the upload's `original_name`.
- Types a browser could run when opened from a public disk (SVG, HTML, XHTML, XML, XSL, JavaScript and PHP) are always stored on the private forms disk.
- Media items are attributed to `uploaded_by` when it is set, never to a visitor who happens to be logged in. Otherwise the media library's own guest upload settings apply.

When the media library is not installed or rejects a file, such as when guest uploads are off and `uploaded_by` is unset, the file is stored on the forms disk instead and a warning is logged.

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

## Visibility and Privacy

File fields have the same **Visibility** setting and per-field privacy settings as other inputs, in the builder's Advanced tab. Because a file is stored as a file and a `form_uploads` row rather than as a submission value, each one is handled with the file in mind.

### Administrative File Fields

With **Visibility** set to **Administrative**, the field is left out of the public form and its validation, and a file sent for it is ignored. Admins add the file on the submission instead:

- **Livewire** — the submission detail's **Administrative Fields** card lists the stored file(s), each with a download link and a **Remove** button that asks for confirmation (its accessible name includes the file name), and a labelled file input. A chosen file replaces the stored one(s) when the administrative fields are saved. The upload's progress and result are announced in a polite live region.
- **React and Vue** — `SubmissionDetail` shows the same list and input, and uploads chosen files in a multipart request when the fields are saved.
- **API** — send the files to the update endpoint as multipart `admin_files[{name}]` (`admin_files[{name}][]` for a multi-file field). PHP only parses multipart bodies on `POST`, so send a `POST` with `_method=PUT`. Each file is checked with the field's **Max File Size**, **Allowed File Types**, image and multi-file limits. To delete files, send their upload IDs in `remove_admin_uploads`; each must be a file of one of the submission's administrative file fields that you may edit.

```bash
curl -X POST https://example.com/api/forms/contact/submissions/42 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -F _method=PUT -F "admin_files[contract]=@contract.pdf"

curl -X PUT https://example.com/api/forms/contact/submissions/42 \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"remove_admin_uploads": [17]}'
```

A required administrative file field only needs a file while the submission has none for it; a stored file is kept until it is replaced or removed. Replacing or removing a file deletes the stored file and its `FormUpload`. These actions need the `update` ability on the submission.

### Do Not Store

The files are stored only for as long as the submission's notifications and webhooks need them. No submission value is saved for the field.

- Notifications that include the submission data attach the files.
- Webhooks receive them in `submission.files`, one entry per file with its `field`, `name`, `mime_type`, `size` and base64 `content`.
- The files never appear in the admin, the API or exports, and their download routes return 404.

Each notification and webhook job holds the files from the moment it is dispatched until it has run, or has failed for the last time; a job that throws keeps them for its retry. When the last job lets go, the files and their upload records are deleted. With no notifications or webhooks, they are deleted as soon as the submission is saved. `forms:prune-submissions` deletes any that are still held a day later, in case a job was lost.

The upload's `pending_deliveries` column counts what still holds the file (run the migrations). `FormUpload::isTransient()` checks it and `FormUpload::stored()` leaves such uploads out of a query.

### Mask in Admin

The file's name, preview and download link are hidden from users who may not reveal masked values. The submission detail and list show `••••` with a **Masked** badge, the attachments list leaves the file out, and the download routes return 403 for those users. In the API the value is masked and has no `upload_id` or `upload_ids`, and the file is left out of `uploads`. Exports leave the file column out unless the user may reveal it. Admins (`is_admin`) may reveal by default; see the `revealMaskedValues` ability and the `ap.forms.canRevealMaskedValues` filter.

### Redact After N Days

`forms:prune-submissions` deletes the stored files and upload records of values older than the period, and marks the values redacted (`redacted_at`). A redacted file value shows as "[Redacted]".

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

Uploads are displayed in the submission detail view with download links. Files of "Do not store" fields are never listed, and files of fields masked in the admin are hidden from users who may not reveal them (see [Visibility and Privacy](#visibility-and-privacy)).

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

This deletes both database records and physical files. It also deletes the files of values past their field's **Redact after (days)** period and any "Do not store" files still held a day after upload (see [Visibility and Privacy](#visibility-and-privacy)).

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
