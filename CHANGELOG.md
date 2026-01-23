# ArtisanPack UI Forms Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-23

### First Stable Release

This is the first stable release of ArtisanPack UI Forms, a comprehensive form builder and management package for Laravel applications built on Livewire 3.

### Changed

- Promoted from beta to stable release
- All features from beta releases are now production-ready

---

## [1.0.0-beta2] - 2026-01-09

### Added

#### Internationalization

- **Full Translation Support**: All user-facing strings throughout the package now use Laravel's `__()` translation function
- **Translatable Field Types**: Field type labels, categories, and descriptions are now translatable
- **Translatable Form Builder**: All form builder UI elements including tabs, buttons, and labels support translations
- **Translatable Form Renderer**: Multi-step form navigation, validation messages, and accessibility announcements are translatable
- **Translatable Components**: Submissions list, notification editor, and all Livewire components support internationalization

### Fixed

- **Translation Conflict Resolution**: Fixed `htmlspecialchars()` error caused by `__('Validation')` conflicting with Laravel's `validation.php` language file on case-insensitive filesystems (macOS). Changed to `__('Validation Settings')` to avoid the collision
- **Multi-Step Form Announcements**: Fixed JavaScript `replace()` to `replaceAll()` for step change announcements, ensuring all placeholder occurrences are replaced in accessibility announcements
- **Export Service Tests**: Updated test expectations to use stable '1'/'0' values for boolean fields instead of localized 'Yes'/'No' strings

### Changed

- **Code Quality Improvements**: Applied Code Rabbit suggestions for improved code consistency and maintainability

## [1.0.0-beta1] - 2026-01-08

### First Beta Release

This is the first beta release of ArtisanPack UI Forms, a comprehensive form builder and management package for Laravel applications built on Livewire 3.

### Highlights

- **Complete Form Builder**: Visual drag-and-drop interface for creating forms
- **20+ Field Types**: Comprehensive set of form fields for any use case
- **Full Test Coverage**: 592 tests ensuring reliability
- **Laravel 11 and 12 Support**: Compatible with current Laravel versions
- **Livewire 3.6+ Compatible**: Built for the latest Livewire features
- **Comprehensive Documentation**: Detailed guides and API reference

### Added

#### Core Features

- **Form Builder Component**: Drag-and-drop Livewire component for visual form creation
- **Form Renderer Component**: Livewire component for displaying forms to users
- **Forms List Component**: Management interface for viewing and managing forms
- **Submissions List Component**: View and manage form submissions
- **Submission Detail Component**: Detailed submission view with file downloads
- **Notification Editor Component**: Configure email notifications per form

#### Field Types

- **Basic Fields**: Text, Email, URL, Phone, Number, Password, Hidden
- **Text Fields**: Textarea, Rich Text Editor (TinyMCE/Trix support)
- **Selection Fields**: Select, Multi-Select, Checkbox, Checkbox Group, Radio, Toggle
- **Date/Time Fields**: Date, Time, DateTime with picker support
- **File Fields**: File Upload with drag-and-drop, Multiple Files
- **Layout Fields**: Heading, Paragraph, Divider, HTML

#### Form Features

- **Multi-Step Forms**: Wizard-style forms with step navigation and progress indicators
- **Conditional Logic**: Show/hide fields and steps based on user input
- **Field Validation**: Required, min/max length, patterns, custom rules
- **Success/Error Messages**: Customizable messages and redirects

#### Submissions

- **Submission Storage**: Store all form submissions with field values
- **File Attachments**: Secure file storage linked to submissions
- **Export to CSV**: Export submissions with field headers
- **Retention Settings**: Automatic pruning of old submissions
- **Prune Command**: `forms:prune-submissions` Artisan command

#### Notifications

- **Admin Notifications**: Email notifications on new submissions
- **Autoresponders**: Automatic replies to form submitters
- **Template Variables**: Dynamic content using form field values
- **Queue Support**: Notifications processed via Laravel queues

#### File Uploads

- **Secure Storage**: Private disk storage with configurable disk
- **MIME Validation**: Whitelist allowed file types
- **Size Limits**: Configurable maximum file sizes
- **Multiple Files**: Support for multiple file uploads per field

#### Webhooks

- **Webhook Endpoints**: Send submission data to external URLs
- **HMAC Signatures**: Secure webhook payloads with signatures
- **Retry Logic**: Automatic retries for failed webhooks
- **Queue Support**: Webhooks processed via Laravel queues

#### Spam Protection

- **Honeypot Fields**: Hidden fields to catch bots
- **Rate Limiting**: Configurable submission rate limits
- **Integration Ready**: Hooks for reCAPTCHA and other services

#### Authorization

- **Form Policy**: Control who can create, view, edit, delete forms
- **Submission Policy**: Control access to submission data
- **Ownership Support**: Restrict access to form owners
- **Admin Bypass**: Allow admins to bypass ownership restrictions

#### Events

- **FormCreated**: Dispatched when a form is created
- **FormUpdated**: Dispatched when a form is updated
- **FormDeleted**: Dispatched when a form is deleted
- **FormSubmitted**: Dispatched when a form is submitted
- **SubmissionUpdated**: Dispatched when a submission is updated
- **SubmissionDeleted**: Dispatched when a submission is deleted

#### Services

- **FormService**: Create, update, duplicate forms
- **FieldService**: Manage form fields
- **StepService**: Manage multi-step form steps
- **SubmissionService**: Process and store submissions
- **NotificationService**: Send email notifications
- **ConditionalLogicService**: Evaluate field/step conditions
- **ExportService**: Export submissions to various formats
- **IntegrationService**: Handle external integrations

#### Extensibility

- **Filter Hooks**: Add custom field types via `forms.field_types` filter
- **Action Hooks**: Hook into form lifecycle events
- **Publishable Views**: Customize all Blade views
- **Publishable Config**: Full configuration customization

### Infrastructure

- **Comprehensive Test Suite**: 592 tests with Pest PHP
- **Code Style**: PHP-CS-Fixer and PHPCS with ArtisanPackUI standards
- **Static Analysis**: PHPStan level 5 analysis
- **GitLab CI/CD**: Multi-PHP version testing (8.2, 8.3, 8.4)
- **Code Coverage**: Coverage reporting with Cobertura format
- **Security Scanning**: SAST, Secret Detection, Dependency Scanning
- **Documentation**: MkDocs deployment to GitLab Pages
- **WordPress-Style Documentation**: Full PHPDoc blocks on all classes and methods
