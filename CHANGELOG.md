# ArtisanPack UI Forms Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- React field-component registry seam so a host can render and edit a host-defined custom field type (e.g. `artisanpack-ui/bookings`' `booking_slot`) without patching vendored source — bringing the React renderer to parity with the server-side `ap.forms.*` extensibility ([#69](https://github.com/ArtisanPack-UI/forms/issues/69)):
  - `registerFieldComponent(type, component)` / `FormRenderer` + `FieldRenderer` `fieldComponents` prop — overlay the built-in renderer map so an unknown `field.type` resolves to a host component instead of rendering `null`.
  - `registerFieldPaletteGroup(group)` / `FieldPalette` `extraGroups` (and `FormBuilder` `paletteExtraGroups`) prop — append builder palette groups for custom types; palette items may carry an `iconPath` (raw SVG path) for an icon outside the built-in set.
  - `registerFieldSettings(type, component)` / `FieldEditor` `customSettings` (and `FormBuilder` `fieldCustomSettings`) prop — contribute a per-type settings panel to the editor's General tab. The panel receives `allFields` (the other fields in the form), mirroring the server-side `ap.forms.fieldSettings` filter's `Form` argument, so it can build controls that map to existing fields (e.g. a booking's name/email/phone selects).
  - `registerFieldCardPreview(type, component)` / `FormBuilder` `fieldCardPreviews` prop — render a live preview inside the field's card on the builder canvas, the React equivalent of the server-side `ap.forms.fieldCardPreview` filter.
  - Across all seams a per-instance prop takes precedence over a module-level registration; renderer components then fall back to the built-in (overlay by type), palette groups append after the built-ins, editor settings render additively below the shared settings, and card previews have no built-in fallback (the generic card shows when none is registered).
- Widened the `FieldType` union to `BuiltInFieldType | (string & {})` so a TypeScript host can legally construct and hold a custom field across `FormField.type`, `StoreFieldRequest.type`, `FieldPaletteItem.type`, and the admin editor props while keeping autocomplete for the built-in types. ([#69](https://github.com/ArtisanPack-UI/forms/issues/69))

## [1.4.0] - 2026-08-20

### Added

- Three builder-extensibility seams so a package that registers a custom field type via `ap.forms.fieldTypes` (e.g. `artisanpack-ui/bookings`' Appointment field) can be a first-class citizen of the drag-and-drop builder ([#63](https://github.com/ArtisanPack-UI/forms/issues/63)):
  - `ap.forms.fieldCategories` filter — the palette now reads `FieldTypes::getCategoriesFiltered()`, so a type filed under a custom category actually appears. The filter existed but was previously dead code the builder never reached. Payload: `(array $categories)`.
  - `ap.forms.fieldSettings` filter — inject custom controls into the field editor, with the field and the form (its other fields) in scope for mapping dropdowns. Payload: `(string $html, FormField $field, Form $form)`.
  - `ap.forms.fieldCardPreview` filter — render a live preview of the field on the builder canvas instead of the bare card. Payload: `(string $html, FormField $field)`.

### Fixed

- `FormSubmitted` is now dispatched after the submission transaction commits. It was previously fired from the `FormSubmission` model's `created` hook, which runs before `SubmissionService::create()` writes the submission's field values, so any listener reading `$submission->values` received an empty submission — most visibly the bookings appointment listener, which found no `booking_slot` value, returned early, and silently created no booking while still reporting success. Implementing `ShouldDispatchAfterCommit` defers delivery until the values (written in the same transaction) are present, matching the event's documented contract. ([#65](https://github.com/ArtisanPack-UI/forms/issues/65))
- Registered the `forms-views` and `forms-config` publish tags the documentation has always instructed. Both were documented but never registered — `forms-views` (documented 16 times) resolved to nothing, and `forms-config` was only reachable via the ecosystem-wide `artisanpack-package-config` tag, which publishes every installed ArtisanPack package's config rather than just this one. `vendor:publish` exits `0` on a tag that matches nothing, so following the documented install steps silently published nothing. The registered `artisanpack-forms-views` and `artisanpack-package-config` tags continue to work as aliases. ([#62](https://github.com/ArtisanPack-UI/forms/issues/62))
- Dropped the `--tag=forms-migrations` instruction from the installation and customization guides. The package loads its own migrations via `loadMigrationsFrom()`, so there was never a tag to publish — `php artisan migrate` is all a consumer needs. ([#62](https://github.com/ArtisanPack-UI/forms/issues/62))
- Added `tests/Feature/PublishTagsTest.php`, which walks the README and every Markdown file under `docs/` and asserts every documented `--tag=` value is a registered publish group, so this drift cannot silently recur.

## [1.3.0] - 2026-07-21

### Added

- Six new `ap.forms.*` hooks for the Wave 5 cross-package standardization ([#58](https://github.com/ArtisanPack-UI/forms/issues/58)):
  - `ap.forms.beforeValidate` action — fires from `FormRequest::prepareForValidation()` just before Laravel validates a package form request. Payload: `(FormRequest $request)`.
  - `ap.forms.validated` action — fires from `FormRequest::passedValidation()` after validation succeeds. Payload: `(FormRequest $request, array $validated)`.
  - `ap.forms.fieldRender` filter — pipes each field's rendered HTML through subscribers before it's echoed in the form-renderer view. Payload: `(string $html, FormField $field)`.
  - `ap.forms.integrationRegistered` action — fires from `IntegrationService::registerIntegration($slug, $config)` so ecosystem packages can observe every integration registration. Payload: `(string $slug, array $config)`.
  - `ap.forms.notificationSubject` filter — mutates outgoing notification email subjects. Payload: `(string $subject, FormNotification $notification, FormSubmission $submission)`. Both `notificationSubject` and `notificationBody` fire AFTER the legacy `notificationMessage` filter (which stays for back-compat), on the already-parsed template.
  - `ap.forms.notificationBody` filter — mutates outgoing notification email bodies. Payload: `(string $body, FormNotification $notification, FormSubmission $submission)`.
- `Support\FiresValidationHooks` trait applied to `StoreFormRequest`, `UpdateFormRequest`, and `SubmitFormApiRequest` wiring the two validation-lifecycle hooks above.
- `Support\FieldRenderer::render()` helper used by the form renderer view to render each field partial and pipe the HTML through `ap.forms.fieldRender`.
- `IntegrationService::registerIntegration(string $slug, array $config = [])` for integration packages to announce themselves during their service provider boot.

### Changed

- Bumped the `artisanpack-ui/hooks` constraint to `^1.3` and dropped every `function_exists('applyFilters' | 'doAction')` guard across `src/`. Hooks 1.3 is now a hard runtime requirement — the 15+ pre-existing guarded sites and the six new Wave 5 sites all call the hook helpers directly.

### Deprecated

- All 18 hooks previously shipped under `forms.*` / snake_case names have been renamed to the ecosystem-standard `ap.forms.*` camelCase convention (Wave 2 of the cross-package hooks standardization). Existing subscribers continue firing via deprecation aliases registered in `Support\HookAliases`, emitting an info-level `HookDeprecations` log entry per unique resolution. **Aliases will be removed in the next major version — migrate subscribers to the new names before then.** Full rename map is documented in the README. Requires `artisanpack-ui/hooks: ^1.2` (deprecation logging is a no-op on 1.2.x and active on 1.3.0+). ([#57](https://github.com/ArtisanPack-UI/forms/issues/57))

## [1.2.0] - 2026-07-08

### Added

- Four opt-in AI agents built on `artisanpack-ui/ai` (v1.0+): `SpamDetectionAgent` (`forms.spam_detection`), `SubmissionSummaryAgent` (`forms.submission_summary`), `ResponseClassificationAgent` (`forms.response_classification`), and `SmartFieldValidationAgent` (`forms.smart_validation`). Registered via `aiFeatures()` on `FormsServiceProvider` and shipped with matching Livewire trigger components (`forms::ai-spam-check`, `forms::ai-submission-summary`, `forms::ai-response-classifier`, `forms::ai-smart-field-validator`). Each agent no-ops when its feature toggle is off, so installs without the AI package are unaffected. ([#50](https://github.com/ArtisanPack-UI/forms/issues/50), [#51](https://github.com/ArtisanPack-UI/forms/issues/51), [#52](https://github.com/ArtisanPack-UI/forms/issues/52), [#53](https://github.com/ArtisanPack-UI/forms/issues/53))
- `sample_count` field on the `SubmissionSummaryAgent` output so callers computing per-theme percentages against a truncated sample (submissions over `SUBMISSION_LIMIT = 200`) can render accurate ratios instead of extrapolating against `total_count`. The Livewire trigger component surfaces a "themes reflect the first N of M submissions" caption when truncation happened.
- Shared `NormalizesLLMInput` trait for the four agents providing `safeJsonEncode()`, `escapeForPrompt()`, and `hashInputFingerprint()` helpers — bounds token spend by dropping `JSON_PRETTY_PRINT`, defuses admin-authored prompt-injection payloads in `form_name` / `field_label` / `field_kind` / `value`, and produces stable cache fingerprints over realistic non-scalar submission payloads (Carbon dates, nested arrays, objects).
- New `docs/ai/` section with an overview page, per-agent references, a configuration guide, and a feature-toggle guide.

### Changed

- `FormsServiceProvider::aiFeatures()` now short-circuits to `[]` when `artisanpack-ui/ai` is not installed, so consumers on `--no-dev` builds never receive class-strings that would autoload a missing `ArtisanPackAgent` base.
- All four AI Livewire components dispatch events using camelCase named parameters (e.g. `submissionId`, `spamScore`, `formName`, `totalCount`, `sampleCount`, `suggestedNew`, `fieldName`) so parent listeners with standard PHP camelCase parameter names hydrate correctly. The dispatched event names themselves are unchanged.
- All four AI Livewire components' public array properties (`$fields`, `$meta`, `$submissions`, `$availableCategories`, `$value`, `$context`) are marked `#[Locked]` so client-side tampering cannot swap the payload mid-run. Livewire still serializes public properties into `wire:snapshot` for state restoration — multi-tenant admin usage must scope inputs to the current viewer, as documented on each component class.
- `SpamDetectionAgent` synthesizes a fallback reason string when a non-ham verdict (`suspicious` or `spam`) has an empty `reasons` list, so the shipped Livewire view never contradicts a positive verdict with the "no reasons" fallback text.
- `SubmissionSummaryAgent` throws `FeatureError` when the model returns an empty `headline` so the caller sees a retryable error rather than a broken empty-headline card.
- `SubmissionSummaryAgent::normalizeThemes` uses an `is_numeric` guard and clamps `count` to `[0, sampleCount]` — non-numeric strings like `"approximately 12"` drop to `0` and hallucinated `999`s cap at the actual sample size.
- `SmartFieldValidationAgent` normalizes the model's `plausible` output via a strict helper that treats the string `"false"`/`"0"`/`"no"`/`"off"` as `false`, so a tool bridge that coerces the JSON enum to a string cannot silently flip the verdict.
- Every Livewire trigger component now calls `report($exception)` in its terminal `catch (Throwable)` arm before setting the localized error string, so production failures (provider 5xx, DNS blips, missing config) reach Sentry / Laravel logs instead of vanishing.
- Every Livewire trigger component now renders a translated generic message from its `catch (FeatureError)` arm instead of surfacing the raw exception string (which leaked the internal feature key and bypassed i18n).
- Dropped PHP 8.2 from the CI matrix because the AI feature suite's dev-time dependency (`artisanpack-ui/ai`) requires PHP 8.3+. The forms package's runtime constraint is still `"php": "^8.2"` — consumers on PHP 8.2 who do NOT install the AI package can continue using the core forms functionality unchanged.
- Refreshed `guzzlehttp/guzzle` and `guzzlehttp/psr7` to patch three medium-severity advisories in test-only transitive dependencies (CVE-2026-55767, CVE-2026-55568, CVE-2026-55766).

### Fixed

- All four agents override `cacheFingerprint()` — the base `ArtisanPackAgent` default throws `InvalidArgumentException` for any array input containing a non-scalar value, which crashed cache-enabled runs on realistic submissions (Carbon timestamps, nested arrays, objects).
- Every agent `buildMessage()` serialization routes through the shared `safeJsonEncode()` helper (`JSON_INVALID_UTF8_SUBSTITUTE`, never returns `false`), so a malformed UTF-8 byte in a single submission field cannot collapse the whole prompt to an empty string.
- Defensive `is_array()` narrowing on Livewire property assignments (`$this->reasons`, `$this->themes`, `$this->notable`, `$this->suggestions`) — a malformed agent output can no longer TypeError on the next Livewire hydration.

## [1.1.3] - 2026-06-09

### Added

- Laravel 13 support. Updated `illuminate/support` constraint to `^11.0|^12.0|^13.0`. Laravel 13 is only selectable on PHP 8.3+ per L13's own `php` constraint. ([#47](https://github.com/ArtisanPack-UI/forms/issues/47))

### Changed

- Tightened the lower bound on `illuminate/support` from the previous overly-permissive `>=5.3` to `^11.0`. No practical impact for existing installs — the package's PHP `^8.2` floor and Livewire 3.6+ requirement already made Laravel 5.3–10.x unreachable.

## [1.1.2] - 2026-05-27

### Added

- React `FormBuilder` Settings panel: the **Slug** field now auto-follows the **Form Name** until the user edits the slug directly, at which point it locks to a manual state and stops mirroring the name. On load, manual mode is derived from whether the persisted slug matches the slugified name, so existing custom slugs aren't clobbered. The field hint copy reflects the current state. ([#40](https://github.com/ArtisanPack-UI/forms/issues/40))

### Fixed

- Database factories are now shipped in the published dist tarball. Previously `/database/factories` was marked `export-ignore` in `.gitattributes`, so installs via `composer require --prefer-dist` autoloaded `ArtisanPackUI\Forms\Database\Factories\*` from a directory that did not exist, causing `Class not found` errors in downstream test suites and seeders. ([#41](https://github.com/ArtisanPack-UI/forms/issues/41))
- Resolved five categories of TypeScript errors surfaced when consumers `tsc`-compiled the published React components: dropped the no-longer-supported `size` prop on `<Input>`/`<Select>` in favor of `className="input-sm"`/`"select-sm"`; added an index signature to `FieldOption` so it satisfies `@artisanpack-ui/react`'s `SelectOption`/`RadioOption` shape; imported `JSX` from `react` (React 19) and narrowed the dynamic heading `Tag` to a `HeadingTag` union in `LayoutField`; converted `GenericConditionalLogic` at the boundary in `FieldEditor` and `NotificationEditor` so the broader `action: string` shape doesn't leak into the narrower `ConditionalLogic`/`NotificationConditionalLogic` setters; and moved `artisanpack-forms.d.ts` into `resources/js/types/` with updated `shared/*` imports so the path resolves consistently in source and in the published `js/vendor/artisanpack-forms/` layout. ([#39](https://github.com/ArtisanPack-UI/forms/issues/39), [#42](https://github.com/ArtisanPack-UI/forms/issues/42))

## [1.1.1] - 2026-05-26

### Changed

- Widened `livewire/livewire` constraint to `^3.6.4|^4.0` so the package can be installed in Livewire 4 applications. The full test suite passes against Livewire 4.3.
- Widened `artisanpack-ui/security` constraint to `^1.0|^2.0` so the package can be installed alongside Security v2.x consumers.
- Moved `ArtisanPackUI\Forms\Database\Factories` from `autoload-dev` to production `autoload` so downstream applications can use the factories in their own test suites.

### Fixed

- `UpdateFormApiRequest` now uses `sometimes` on every field so PATCH-style partial updates (e.g. the FormBuilder's auto-save sending only a changed `slug`) don't trip `required` on untouched fields.
- React `FormBuilder` / `SubmissionsList` / `SubmissionDetail` admin components now address forms by `form.id` in API URLs instead of `form.slug`. Using the in-flight slug caused the auto-save PUT to 404 the moment a user renamed the slug. Keying by the stable primary key fixes that and lets the route key be opaque to the UI. (Consumers also need a `Route::bind('form', ...)` that resolves numeric IDs, or to change `Form::getRouteKeyName()` to `'id'`.)
- React `FieldEditor` now mirrors the field locally so every keystroke renders immediately. Previously the inputs were controlled by the parent's `field` prop, which only updated after the 500 ms debounced save round-trip — characters typed during that window were dropped.
- React `FormBuilder` left sidebar now stays populated (palette by default, settings on demand) while a field is selected. Previously selecting a field set `activePanel = 'editor'`, which blanked the sidebar because nothing rendered for that case.
- `SubmissionService::isRateLimited()` / `recordAttempt()` now honor `artisanpack.forms.spam_protection.rate_limit.attempts` / `.decay` from the package config. Previously both methods used hardcoded constants (5 attempts, 60-second window), so the published config values had no effect.

## [1.1.0] - 2026-04-08

### Added

#### REST API

- **Full REST API**: Complete set of RESTful endpoints for forms, fields, steps, submissions, and notifications under `api/v1/forms`
- **Public Endpoints**: Unauthenticated endpoints for rendering forms (`GET /render`) and submitting responses (`POST /submit`) with honeypot and rate limiting
- **Authenticated Endpoints**: Full CRUD for forms, fields, steps, submissions, and notifications secured with Laravel Sanctum
- **Submission Management API**: Bulk actions (mark read/unread/spam, delete), CSV export, and file download endpoints
- **Field & Step Reordering**: Dedicated reorder endpoints for drag-and-drop ordering via API
- **Pagination**: Consistent paginated responses with configurable per-page via `artisanpack.forms.api.per_page`

#### API Resources

- **FormResource**: JSON transformation for form models with conditional relationship loading and submission counts
- **FormRenderResource**: Public-facing form payload with honeypot and display configuration
- **FormFieldResource**: Field serialization with validation rules, conditional logic, and options
- **FormStepResource**: Step serialization with nested fields
- **FormSubmissionResource**: Submission data with values and uploads
- **FormSubmissionValueResource**: Individual field value serialization
- **FormUploadResource**: File upload metadata with download URLs
- **FormNotificationResource**: Notification configuration serialization
- **PaginatedResourceCollection**: Consistent pagination wrapper with meta and links

#### Form Request Validation (API)

- **StoreFormApiRequest / UpdateFormApiRequest**: Form creation and update validation
- **StoreFieldApiRequest / UpdateFieldApiRequest**: Field management validation
- **StoreStepApiRequest / UpdateStepApiRequest**: Step management validation
- **ReorderFieldsApiRequest / ReorderStepsApiRequest**: Reorder validation
- **SubmitFormApiRequest**: Public form submission validation
- **UpdateSubmissionApiRequest**: Submission metadata update validation
- **BulkSubmissionApiRequest**: Bulk action validation
- **StoreNotificationApiRequest / UpdateNotificationApiRequest**: Notification validation

#### TypeScript Type Definitions

- **Published Type Definitions**: Full TypeScript definitions for all form models, API request/response types, field types, conditional logic operators, and pagination structures via `artisanpack-forms.d.ts`

#### React Components

- **FormRenderer**: Full-featured React form renderer with API integration, multi-step support, conditional logic, client-side validation, honeypot protection, and customizable loading/error/success states
- **MultiStepForm**: Multi-step form wrapper with progress bar and step navigation
- **Field Components**: TextField, EmailField, PhoneField, NumberField, UrlField, TextareaField, SelectField, RadioField, CheckboxField, CheckboxGroupField, SelectMultipleField, FileField, DateField, TimeField, HiddenField, HeadingField, ParagraphField, DividerField, HtmlField
- **useForm Hook**: Manages form state, validation, step navigation, file uploads, and submission
- **useApi Hook**: Type-safe API client with error handling (ApiError, ApiValidationError)
- **useAutoSave Hook**: Automatic form draft saving
- **Admin Components**: FormsList, FormBuilder, FieldEditor, FieldPalette, ConditionalLogicEditor, NotificationEditor, SubmissionsList, SubmissionDetail

#### Vue Components

- **FormRenderer**: Vue 3 form renderer with Composition API, slots for loading/error/success customization, and full feature parity with React
- **MultiStepForm**: Multi-step form wrapper with progress bar and step navigation
- **HoneypotField**: Dedicated honeypot field component
- **Field Components**: TextField, ChoiceField, AdvancedField, LayoutField, FieldRenderer
- **useForm Composable**: Reactive form state management with step navigation and submission
- **useApi Composable**: Type-safe API client with error handling
- **useAutoSave Composable**: Automatic form draft saving
- **Admin Components**: FormsList, FormBuilder, FieldEditor, FieldPalette, ConditionalLogicEditor, NotificationEditor, SubmissionsList, SubmissionDetail

#### Shared Frontend Utilities

- **Validation Module**: Client-side field validation for all 18+ field types with min/max, pattern, file size/type, and required checks
- **Conditional Logic Module**: Client-side conditional logic evaluation with 19 operators, AND/OR logic, and show/hide actions

#### Frontend Scaffolding Command

- **`forms:install-frontend`**: Artisan command to publish React or Vue components, shared utilities, and TypeScript types to `resources/js/vendor/artisanpack-forms/` with `--stack` and `--force` options

#### CI/CD

- **GitHub Actions CI**: Lint job (PHP-CS-Fixer + PHPCS) and matrix test job (PHP 8.2, 8.3, 8.4)
- **GitHub Actions Release**: Separate release workflow triggered by version tags with CHANGELOG extraction, GitHub Release creation, and Packagist update

### Fixed

- **Select Multiple Field**: Added missing `select_multiple` field view, factory state, and test
- **Pagination Links**: Preserved query parameters in pagination links for API resources

### Changed

- **GitHub Migration**: Migrated repository from GitLab to GitHub with updated issue templates, PR templates, and CI workflows

---

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
