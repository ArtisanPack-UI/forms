# ArtisanPack UI Forms Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `registerFieldSettings( type, component, { position } )` takes an optional placement for a custom settings panel in the React `FieldEditor`. `'before-basics'` renders the panel above Width and CSS Classes, under a **Field Basics** divider. `'after-basics'` (the default) keeps today's position at the end of the General tab. The `customSettings` / `fieldCustomSettings` props also accept `{ component, position }`. New exports: `resolveFieldSettings`, `getRegisteredFieldSettingsRegistration`, and the `FieldSettingsPosition`, `FieldSettingsOptions`, `FieldSettingsRegistration` and `CustomFieldSettingsEntry` types. ([#76](https://github.com/ArtisanPack-UI/forms/issues/76))
- The Livewire builder has content editors for paragraph and HTML blocks, and edits a heading's text in a **Heading Text** field. Help Text is hidden for these three types. ([#82](https://github.com/ArtisanPack-UI/forms/issues/82))
- `FormField::getFileMaxSize()`, `getFileAllowedTypes()`, `getLayoutContent()` and `sanitizeHtml()`, plus a shared `resources/js/shared/fieldSettings.ts` that mirrors them for the React and Vue components.
- All three builders can set a text area's **Rows**, a heading's **Heading Level** (H2–H6), a divider's **Line Style** and **Spacing**, and a time field's **Min Time**, **Max Time** and **Step (seconds)**. The React and Vue renderers now apply rows, time step and divider style, and read the stored `h2`–`h6` heading level (they rendered every heading as an `h2` before). ([#83](https://github.com/ArtisanPack-UI/forms/issues/83))
- Single checkboxes and toggles have **Checked Text** and **Unchecked Text** settings (default "Yes" / "No") for how the answer is shown in submissions, notifications and exports. ([#85](https://github.com/ArtisanPack-UI/forms/issues/85))
- `FormSubmissionValue::$display_values` (also `display_values` in the API) lists the option labels for an array value, and `FormSubmissionValue::getRawValue()` returns the stored value without label mapping. ([#85](https://github.com/ArtisanPack-UI/forms/issues/85))
- Field-type handlers: `ArtisanPackUI\Forms\Contracts\FieldType` and the `AbstractFieldType` base class bring together everything specific to a field type: definition, settings schema, validation rules and messages (including rules for nested inputs), value normalization, display, CSV export columns, merge tags and view. Each built-in type has a handler (`FieldTypes::HANDLERS`), and `FieldTypes::handler( $type )` returns the handler for any type. `ap.forms.fieldTypes` accepts a handler class name or instance as well as an array definition. Validation, storage, submission display, the CSV export, merge tags and Blade rendering now ask the handler, with no change to built-in behaviour. An array type's `validation_options` (`min`, `max`, `pattern`, `step`, `max_size`, `allowed_types`, `min_date`, `max_date`) still give it builder controls. ([#91](https://github.com/ArtisanPack-UI/forms/issues/91))
- Field settings schema: each field type lists its own settings in its definition's `settings`. The Livewire, React and Vue builders render a field's type-specific settings from the schema, so a custom type gets builder controls without any frontend code. The API exposes the schema as `settings_schema` on each field. The public render payload leaves it out. `ArtisanPackUI\Forms\Support\FieldSettings` reads, parses and stores settings, and `fieldSettings.ts` mirrors it. The Livewire builder's `updateSetting( key, value )` replaces the per-setting methods added earlier in this release. ([#91](https://github.com/ArtisanPack-UI/forms/issues/91))
- Rating field type: a star rating with **Max Rating** (1–10, default 5), **Allow half stars** and **Star Size** settings, in all three builders and renderers. Values are validated as `numeric|between:0,{max}` in whole or half steps, and a required rating must be above 0. A rating shows as "4 / 5" in submissions and notifications, exports as the number, and works with the numeric conditional-logic operators. The renderers use a labelled radio group with per-star labels, arrow-key navigation and a visible focus ring. An app that registered its own `rating` type through `ap.forms.fieldTypes` now gets the built-in rating validation; its array definition still changes the label and other definition entries. ([#92](https://github.com/ArtisanPack-UI/forms/issues/92))
- Read-only fields: a **Read-only** toggle in the builders' Advanced tab for every type that takes a default value. Text-like inputs render `readonly`. Choices and ratings render `aria-readonly` and cannot be changed. The server ignores whatever the client submits for a read-only field and stores its default value, which the new `ap.forms.readOnlyValue` filter can supply. Read-only values are applied after the `ap.forms.submissionData` filter, so use `ap.forms.readOnlyValue` rather than that filter to set them. Conditional logic is evaluated against the read-only value, and a read-only field that conditional logic hides is not stored. `FormField::isReadOnly()` and `getReadOnlyValue()`, and `is_read_only` and `read_only_value` in the API. ([#99](https://github.com/ArtisanPack-UI/forms/issues/99))
- The React and Vue admin components can be translated. `t()` and `setTranslations()` are exported from both entry points, and `Forms::jsTranslations()` returns every admin string translated through the host's JSON language files, ready to pass to `setTranslations()`. ([#90](https://github.com/ArtisanPack-UI/forms/issues/90))
- Toggle field type: an on/off switch with **Size**, **Color**, **On Text** and **Off Text** settings, in all three builders and renderers. It validates like a single checkbox (`boolean`, and `accepted` when required), shows and exports its on/off text (default "Yes" / "No"), can be read-only, and works with the **Is checked** / **Is unchecked** conditional-logic operators. The renderers use a checkbox with `role="switch"`. `ToggleField` is exported from the React entry point. ([#93](https://github.com/ArtisanPack-UI/forms/issues/93))
- `autocomplete` tokens (WCAG 2.2 SC 1.3.5): email, phone and URL fields default to `email`, `tel` and `url`, and an **Autocomplete** select in the Advanced tab of text, email, phone, number, URL, textarea, select and date fields offers every WHATWG autofill token plus `on` and `off`. All three renderers set the attribute. `FormField::getAutocomplete()`, and `autocomplete` on each field in the API. ([#94](https://github.com/ArtisanPack-UI/forms/issues/94))
- Dynamic population: an **Allow field to be populated dynamically** toggle and **Parameter Name** (defaulting to the field name) in the Advanced tab. A populated field takes its starting value from the renderer's `prefill` (`<livewire:forms::form-renderer :form="$form" :prefill="[...]" />`, or the React / Vue `prefill` prop), then the page's query string, then the new `ap.forms.fieldValue.{parameter}` filter, then its default value. Default values accept the merge tags `{user:email}`, `{user:name}`, `{date}`, `{embed_url}`, `{referer}`, `{ip}` and `{query:name}`, listed beside the Default Value input. Populated values are sanitized; choice fields only take a value matching an option (by value or label), and checkbox groups take comma-separated values. Read-only fields are not populated from the prefill or query string, and their page tags are left blank. The render API returns each field's `resolved_default_value` and `dynamic_parameter`; the React and Vue renderers fill in the page tags and query string. ([#95](https://github.com/ArtisanPack-UI/forms/issues/95))
- Custom validation messages: **Custom Error Message** and **Required Message** settings in the Validation tab. The error message replaces every validation message except the required one, which the required message replaces, on the server (Livewire and the submit API) and in `validation.ts`. Both may be translation keys. `FormField::buildValidationMessages()`, and `error_message` / `required_message` in the API. ([#96](https://github.com/ArtisanPack-UI/forms/issues/96))
- Admin labels: an **Admin Label** setting in the Advanced tab, shown instead of the label in the submissions list and detail views, CSV export headers and admin notifications. Autoresponders keep the public label. It is stored with each submitted value in a new `form_submission_values.field_admin_label` column (run the migrations). `FormField::getAdminLabel()`, `FormSubmissionValue::$display_label` and `getNotificationLabel()`, and `field_admin_label` in the API. ([#97](https://github.com/ArtisanPack-UI/forms/issues/97))
- Label and description placement: **Label Placement** (top, left from the `md` breakpoint up, or hidden) and **Description Placement** (above or below the input) in the General tab, with form-wide defaults on the Blade settings page and in the React and Vue builders (`label_placement` / `description_placement` on the form API, stored in `settings`). A hidden label stays in the accessibility tree (`sr-only`), and the input's `aria-describedby` references the description wherever it is placed. The Blade partials render the description with its `hint-{name}` ID, which the inputs already pointed at. The render API returns each field's `label_placement` and `description_placement`; a field without either keeps the React / Vue `display.label_position` behaviour. `FormField::getLabelPlacement()` and `getDescriptionPlacement()`, and the `FieldLayout` component in both entry points. ([#98](https://github.com/ArtisanPack-UI/forms/issues/98))
- No duplicates: a **No duplicates** toggle in the Validation tab of text, email, phone, number, URL and hidden fields rejects a value already submitted for the field. Email addresses compare case-insensitively; spam and quarantined submissions are ignored. The check is the `forms_no_duplicates` validation rule, so the custom error message applies. ([#100](https://github.com/ArtisanPack-UI/forms/issues/100))
- Per-field privacy, in the Advanced tab of every input type except file uploads. **Do not store**: the value reaches notifications and webhooks for the submission (carried in an encrypted job payload) but is never saved. **Mask in admin**: the submission detail, list and API show only the last four characters (`••••1234`; values under eight characters are masked in full), searches skip the field and exports leave it out, unless the user may reveal masked values. Admins (`is_admin`) may by default; the `ap.forms.canRevealMaskedValues` filter and the `revealMaskedValues` policy ability decide. **Redact after (days)**: `forms:prune-submissions` clears stored values older than the period, whether or not a retention period is set, and records it in a new `form_submission_values.redacted_at` column (run the migrations); a redacted value shows as "[Redacted]". `is_masked` and `is_redacted` on each value in the API. ([#101](https://github.com/ArtisanPack-UI/forms/issues/101))
- Administrative fields: a **Visibility** setting (visible or administrative) in the Advanced tab. Administrative fields are left out of the public renderers, their validation and the render API, and a value submitted for one is dropped. Admins fill them in on the submission detail in all three admin UIs; the submission API returns `administrative_fields` and takes `admin_values` on update, validated with each field's rules. Fields masked from the user are not offered for editing. ([#102](https://github.com/ArtisanPack-UI/forms/issues/102))
- "Other" choice: radio and checkbox group fields have an **Enable "Other" choice** toggle and **"Other" Label**. The choice comes last and reveals a text input labelled "Other, please specify", which is required while the choice is selected (up to 255 characters). The typed text is stored as the radio value or in the checkbox group's `value_array`, and shown as "Other: …". Conditional logic and notification conditions can test for the choice (`__other__`), which the condition builders offer. Renderers submit the typed text in place of the choice; the bare `__other__` placeholder fails the `forms_other_choice` rule. `other_choice_label` on each field in the API. ([#103](https://github.com/ArtisanPack-UI/forms/issues/103))
- Bulk add and predefined choices: a **Bulk Add / Predefined Choices** editor in the options editor of all three builders takes one choice per line (`value|label`, or a label that doubles as the value) and can **Replace Options** or **Add to Options**. It can insert built-in lists of countries (ISO 3166-1 codes), U.S. states, Canadian provinces and territories, months, days of the week, age ranges and satisfaction, agreement and importance scales; values stay the same in every locale and labels are translated. Apps add their own lists through the new `ap.forms.predefinedChoices` filter. `ArtisanPackUI\Forms\Support\PredefinedChoices`, the Livewire builder's `applyBulkChoices( text, mode )`, and a `GET {prefix}/{form}/fields/predefined-choices` endpoint for the React and Vue builders. ([#104](https://github.com/ArtisanPack-UI/forms/issues/104))
- Choice field options: each option in the options editor has a **Selected by default** checkbox (one for a radio or select, any number for a checkbox group or multi-select); marked options take precedence over the default value and are what a read-only choice submits. Radio and checkbox groups have a **Choice Layout** setting (stacked, inline, two or three columns, filled row by row so the reading order matches the DOM) and a **Randomize choice order** toggle, shuffled per visitor (the Livewire renderer keeps a locked seed, so re-renders keep the order; an "Other" choice stays last). Checkbox groups have a **Show "Select All"** toggle, which adds a checkbox that is checked when every choice is and mixed when some are. All three renderers, plus `FormField::getDefaultOptionValues()`, `getChoiceLayout()`, `getRenderOptions()` and the `shared/choices.ts` helpers. ([#105](https://github.com/ArtisanPack-UI/forms/issues/105))
- Searchable select: an **Enable search** toggle on select and multi-select fields renders an editable combobox following the WAI-ARIA combobox pattern (`role="combobox"`, a listbox popup, `aria-activedescendant`, a polite status announcing the number of results, arrow keys, Enter, Escape and Home/End). A multi-select shows its choices as removable buttons. The Livewire renderer uses an Alpine combobox partial rather than `x-artisanpack-choices`, which has no combobox semantics; React and Vue export a `Combobox` component. ([#106](https://github.com/ArtisanPack-UI/forms/issues/106))
- Dynamic choices: register choice sources through the new `ap.forms.choiceSources` filter (`key => ['label' => …, 'resolver' => callable]`; the resolver gets the field and returns its choices). A **Load Choices From** setting on the four choice types picks one; the field's options are then the resolved choices wherever they are read (rendering, display, conditional logic, the API), submitted values must be one of them (an `in:` rule, skipped when the field offers an "Other" choice), and the options editor shows them read-only. Resolved choices are kept for the rest of the request; a failing resolver gives no choices and is logged. `ArtisanPackUI\Forms\Support\ChoiceSources` and `FormField::getChoicesSource()`. ([#107](https://github.com/ArtisanPack-UI/forms/issues/107))
- Composite field infrastructure for fields made of several inputs stored together, such as a name or an address. Extend `ArtisanPackUI\Forms\FieldTypes\CompositeFieldType` and list the inputs in the definition's `sub_inputs` (key, label, placeholder, enabled and required by default, `autocomplete` token, input `type`, extra rules). The builders show a table to show, relabel, set the placeholder of and require each input. Renderers bind each input to `formData.{field}.{key}` inside a `<fieldset>` whose legend is the field label, each with its own label (**Sub-Label Placement**: below, above or hidden), `autocomplete` token and error, plus a field-level summary. Rules are nested (`formData.{field}.{key}`) and mirrored in `validation.ts`; the value is stored as a map in `value_array`, shown as the filled inputs joined, exported as one column per input or one formatted column (**Export Format**), offers `{field}` and `{field:key}` merge tags, and is returned as an object by the API, webhooks and the JSON export. Conditional logic, notification conditions and the condition builders can test one input as `{field}.{key}`. ([#108](https://github.com/ArtisanPack-UI/forms/issues/108))
- Name field: a composite of prefix, first, middle, last and suffix (`honorific-prefix`, `given-name`, `additional-name`, `family-name`, `honorific-suffix`), with a **Name Format** preset: **Simple** (one `name` input), **Normal** (the default: first and last shown, the other parts off until turned on) or **Extended** (all five). The prefix is a select of the field's **Prefix Choices** (default Mr., Mrs., Ms., Mx., Dr., Prof., Rev.); the suffix is free text or a select of **Suffix Choices**. Shown as the filled parts joined with spaces, with `{name}` and `{name:first}`-style merge tags and one export column per enabled part. ([#109](https://github.com/ArtisanPack-UI/forms/issues/109))
- Address field: street, address line 2, city, state / province / region, postal code and country (`address-line1`, `address-line2`, `address-level2`, `address-level1`, `postal-code`, `country-name`), each with its own toggle, sub-label and required flag. **Address Type**: International (free-text state, country select), United States (state select, ZIP code check, country fixed to the US) or Canada (province select, postal code check, country fixed to Canada). **Default Country**, **Default State / Province** and **Hide country** (stores the default country without asking). The country list comes from the new `ap.forms.countries` filter, which the Countries predefined list now shares. Shown as a multi-line postal address (line breaks kept in the HTML notification table), with merge tags per part. Inputs carry `data-forms-part` and the Livewire partial renders the new `ap.forms.addressAutocomplete` filter's HTML, so address autocomplete providers can hook in. ([#110](https://github.com/ArtisanPack-UI/forms/issues/110))
- Email confirmation: an **Enable email confirmation** toggle on email fields, with **Email Sub-Label**, **Confirm Sub-Label** and **Confirm Placeholder** settings. The field renders "Enter Email" and "Confirm Email" inputs; the confirmation is submitted as `{name}_confirmation` and checked with Laravel's `confirmed` rule (and in `validation.ts`). Only the email is stored, so display, notifications and exports are unchanged; the confirmation never reaches the `ap.forms.submissionData` filter. Pasting is not blocked. A populated email is confirmed with the same value. ([#111](https://github.com/ArtisanPack-UI/forms/issues/111))
- Consent field: a checkbox with **Checkbox Text** and a **Consent Description** (links and basic formatting allowed, sanitized with kses), `accepted` when required. The value is stored with the text and description shown, a SHA-256 revision hash and a timestamp (`value_array`: `consented`, `text`, `description`, `revision`, `consented_at`); set the new `privacy.consent.store_text` config (`FORMS_CONSENT_STORE_TEXT`) to false to keep only the hash and time. Submissions show "Consented" with a **View consent text** disclosure; notification conditions read a stored consent as checked or unchecked. ([#112](https://github.com/ArtisanPack-UI/forms/issues/112))
- CAPTCHA field: a challenge authors place in the form, with Cloudflare Turnstile, hCaptcha, Google reCAPTCHA v2 (checkbox or invisible) and reCAPTCHA v3 (score) drivers behind the new `ArtisanPackUI\Forms\Contracts\CaptchaDriver` interface. Keys, the default driver, the request timeout and the v3 score threshold live under `spam_protection.captcha` in `config/forms.php`; apps register more drivers through the `ap.forms.captchaDrivers` filter. Each field picks a **Provider**, **Widget Size** and **Theme**. The token is checked with the provider by `CaptchaVerifier` once, after every field has validated and just before the submission is saved, in both the Livewire renderer and the submit API (a failure answers 422 under `data.{field}` and resets the widget); an unreachable provider fails the check. The token is never stored, exported, offered as a merge tag or used in conditional logic. A provider without keys renders nothing, and the honeypot, timing and rate-limit checks still run. ([#113](https://github.com/ArtisanPack-UI/forms/issues/113))
- Composite fields: a sub-input may be a `select` with `options`, span the whole row (`wide`), and start with a **Default** value or be filled from a **Parameter** (the renderer prefill, the query string, then the `ap.forms.fieldValue.{parameter}` filter); both are new columns in the builders' sub-input table. A type can change its inputs per field by overriding `CompositeFieldType::subInputDefinitions()`. Settings schemas accept a `textarea` input. ([#109](https://github.com/ArtisanPack-UI/forms/issues/109), [#110](https://github.com/ArtisanPack-UI/forms/issues/110))
- Multi-file upload: an **Allow multiple files** toggle on file fields, with **Max Files**, **Max Total Size (KB)** and the per-file **Max File Size**. The Livewire renderer shows a drag-and-drop zone around a keyboard-operable file input; each file uploads on its own with a progress bar and a remove button (`FormRenderer::removeFile()`), and a polite live region announces progress and completion. React and Vue list the chosen files with remove buttons and submit them as `files[{name}][]`. Each file is stored as its own `form_uploads` row; the submission value points at the first in `upload_id` and lists every upload ID in `value_array` (`FormSubmissionValue::getUploads()`, `upload_ids` in the API). Submissions, notifications and exports list every file. File fields also get **Min / Max Image Width and Height** (the `forms_image_dimensions` rule; other files pass), and the total size is checked by `forms_total_size`. ([#114](https://github.com/ArtisanPack-UI/forms/issues/114))
- Input masks: an **Input Mask** setting on text and phone fields with US Phone, ZIP, ZIP+4, SSN and Date presets and a **Custom Mask** (`9` digit, `a` letter, `*` letter or digit). The Livewire renderer uses Alpine's mask plugin and React and Vue a matching `mask.ts`; the server checks the whole mask with a `regex` rule. The expected format is shown under the input and referenced by its `aria-describedby`. `FormField::getInputMask()` and `getInputMaskHint()`, and `input_mask` in the API. ([#115](https://github.com/ArtisanPack-UI/forms/issues/115))
- Character and word counters: text and textarea inputs get `minlength` / `maxlength` from their length rules in all three renderers, and a **Show character counter** setting adds a live counter tied to the input with `aria-describedby`, announced politely once typing pauses. Textareas get a **Max Words** rule (`forms_max_words`, checked on the server and in `validation.ts`), and their counter then counts words. ([#116](https://github.com/ArtisanPack-UI/forms/issues/116))
- Phone formats: a **Phone Format** setting (Any, US or International) and a **Default Country** on phone fields. US and International numbers are validated with `propaganistas/laravel-phone` (a new dependency), stored in E.164 form and shown formatted. International fields show a country selector: the Livewire renderer submits it as `{name}_country` (not stored), and React and Vue add the calling code to the number. `phone` in the API lists the countries with their calling codes. ([#117](https://github.com/ArtisanPack-UI/forms/issues/117))
- Number options: a **Number Format** setting (`9,999.99`, `9.999,99` or Currency) that turns the input into a text input with a decimal keypad and converts the value to a plain decimal before validation and storage, a **Whole numbers only** rule (`multiple_of:1`), and **Prefix** / **Suffix** text, given to screen readers through an `aria-describedby` description. `affix_description` in the API. ([#118](https://github.com/ArtisanPack-UI/forms/issues/118))
- Email validation options: an **Email Validation** setting (Standard RFC, RFC with a DNS lookup, or Strict) and **Allowed Domains** / **Blocked Domains** lists, which match subdomains too and are checked by the `forms_email_domain` rule on the server and in `validation.ts`. ([#119](https://github.com/ArtisanPack-UI/forms/issues/119))
- URL options: an **Allowed Schemes** setting (default `http` and `https`), and an address typed without a scheme, such as `example.com`, gets `https://` before validation and storage; the renderers add it when the input loses focus. ([#120](https://github.com/ArtisanPack-UI/forms/issues/120))
- Rich text textareas: a **Use rich text editor** toggle shows a small editor with a keyboard-operable, labelled toolbar (bold, italic, underline, lists, links) in all three renderers. The HTML is sanitized with `kses()` when stored and shown, length and word limits count its text (`forms_min_text`, `forms_max_text`, `forms_max_words:{max},html`), and text emails, exports and merge tags get plain text. The markup is capped at 65,535 characters, and default, populated and read-only values are sanitized before an editor shows them. `FormSubmissionValue::$display_html` (`display_html` in the API) gives the sanitized HTML to the HTML notification table and the submission views. ([#121](https://github.com/ArtisanPack-UI/forms/issues/121))
- Date constraints: **Min Date** and **Max Date** accept relative dates (`today`, `+30 days`, `-18 years`), an **Allowed Dates** setting rejects past or future dates, **Disable weekends** and **Disabled Dates** reject days (`forms_disabled_dates`), and **Minimum Age** / **Maximum Age** check birthdates (`forms_min_age`, `forms_max_age`). The same limits apply in `validation.ts` and the native `min` / `max` attributes. `FormField::getDateLimits()` and `getDateBounds()`. ([#122](https://github.com/ArtisanPack-UI/forms/issues/122))
- Date and time input modes: date fields can use month, day and year dropdowns or number fields instead of the native picker, and time fields hour, minute and AM/PM dropdowns, grouped in a fieldset with a legend in all three renderers. A **Date Format** (`MM/DD/YYYY`, `DD/MM/YYYY`, `YYYY-MM-DD`) orders the date inputs and formats dates in submissions, and a **Time Format** (12- or 24-hour) does the same for times. Dates are still stored as `YYYY-MM-DD` and times as `HH:MM`. ([#123](https://github.com/ArtisanPack-UI/forms/issues/123))
- File upload options: the note under a file input lists the **Allowed File Types** (as `PDF, DOCX`) next to the size limits, and the input's `aria-describedby` references it in all three renderers (`FormField::getFileLimitsHint()`). A **Storage** setting (Advanced tab) can save a field's uploads with `artisanpack-ui/media-library` instead of the private forms disk, in the `uploads.media_library.folder_id` folder and attributed to `uploads.media_library.uploaded_by`; the upload records the media item in a new `form_uploads.media_id` column (run the migrations), and deleting or pruning it deletes the media item. Media library files follow its disk's visibility, which is public by default, so they are saved under the extension found from their contents and SVG, HTML, XML, JavaScript and PHP files stay on the private forms disk, as does any file the media library cannot take. `Support\MediaLibraryStorage`, `FormField::usesMediaLibraryStorage()` and `FormUpload::isInMediaLibrary()`. ([#124](https://github.com/ArtisanPack-UI/forms/issues/124))
- Section layout element: a **Section** groups the fields after it, up to the next section or the end of the step, in a `<fieldset>` whose `<legend>` is its title, with a **Description**. Conditional logic on a section shows or hides every field in it (on the server, in the Livewire renderer and in `conditionalLogic.ts`; a CAPTCHA stays shown), and all three builders offer it. A **Collapsible** section's title is a disclosure button (`aria-expanded`, `aria-controls`) that can **Start collapsed** and opens on an error. `Support\Sections`, `shared/sections.ts`, `FieldTypes::supportsConditionalLogic()`, and the React `SectionGroup` / Vue `SectionField` components. ([#125](https://github.com/ArtisanPack-UI/forms/issues/125))
- Richer layout content: paragraphs keep basic formatting (bold, italic, underline and `http` / `https` / `mailto` links, sanitized with `kses()`; `content_html` in the render API), HTML blocks fill in `{field_name}` and `{field_name:key}` merge tags with the visitor's escaped values as they type (`Support\LiveMergeTags`, `shared/liveMergeTags.ts`, and a `FormValuesContext` / `FORM_VALUES_KEY` for React and Vue), and headings have an **Alignment** setting. ([#126](https://github.com/ArtisanPack-UI/forms/issues/126))
- List field: rows of one or more columns that visitors add and remove, for attendees, references or line items. **Columns** takes one `Label | type | options` line per column (text, email, number or select), with **Row Label**, **Min Rows**, **Max Rows** and **Export Format** (one row per line or JSON). Blank rows are ignored and each cell is checked by its column type; the rows are stored in `value_array`, shown as a table in submissions and HTML notifications, and as one row per line elsewhere. Rows are labelled groups with descriptive add and remove buttons ("Remove Attendee 2"); focus moves to a new row and to the add button after a removal, and a live region announces each change. `ListFieldType`, the Livewire renderer's `addListRow()` / `removeListRow()`, `shared/list.ts` and the React / Vue `ListField`. ([#127](https://github.com/ArtisanPack-UI/forms/issues/127))
- Date range and date and time fields: **Date Range** (`date_range`) is a composite of start and end dates, checked by the new `forms_date_range` rule (both dates, end on or after start) and limited by **Min Date** / **Max Date**; **Date & Time** (`datetime`) is a native `datetime-local` input stored as `YYYY-MM-DD HH:MM`, bounded by its min and max dates and shown in its date and time formats. Composite sub-inputs may now be `date`. ([#128](https://github.com/ArtisanPack-UI/forms/issues/128))
- Signature field (`signature`): visitors draw on a canvas pad (mouse, finger or stylus, with **Clear signature**) or type their full name instead, which is the accessible alternative. **Pad Height** sets the pad size. A drawing is submitted as a PNG data URL and stored as a `form_uploads` row (`signature.png`) on the forms disk, which the value points at with `upload_id`; a typed name is stored as text. The new `forms_signature` rule accepts a PNG of up to 512 KB and 4000 pixels a side (read from its header) or a name of up to 255 characters. Submissions show the drawing as an image, HTML notifications embed it as an inline (`cid:`) attachment, and text views and exports read "Signed (drawn)" or `Signed as "…" (typed)`. `SignatureFieldType`, `shared/signature.ts` (with a `SignaturePad` class) and the React / Vue `SignatureField`. ([#129](https://github.com/ArtisanPack-UI/forms/issues/129))
- Slider field (`slider`): a native range input with **Minimum**, **Maximum**, **Step**, **Hide value** and a **Value Prefix** / **Value Suffix** for the value label, which `aria-valuetext` also reads ("$40"). It starts at its default value or minimum, is validated as `numeric|between` plus the new `forms_step:{base},{step}` rule (whole steps from the minimum, with floating-point tolerance), shows with its prefix and suffix, exports the number and works with the numeric conditional-logic operators. `SliderFieldType`, `shared/slider.ts` and the React / Vue `SliderField`. ([#130](https://github.com/ArtisanPack-UI/forms/issues/130))
- Likert / matrix field (`matrix`): statements (**Rows**, one per line) against a scale (**Columns**, `Label` or `Label | value`, defaulting to a five-point agreement scale), or a **Single scale** layout. A grid is stored in `value_array` keyed by row, for the answered rows, each as `{statement, answer, value}`: the statement and answer label as they read when submitted, and the column value; a required grid needs every row answered, with errors on the row. Submissions show one row per line and the CSV export has a column per row. The renderers use a table whose radios are named by their row and column headers, one radio group per row. `MatrixFieldType`, `shared/matrix.ts` and the React / Vue `MatrixField`. ([#131](https://github.com/ArtisanPack-UI/forms/issues/131))
- Net Promoter Score field (`nps`): a 0–10 radio group shown as buttons, with **Low Label** and **High Label** under the ends (0 and 10 are named with them). Scores are validated as `integer|between:0,10`. **Show category** tags each score as a promoter, passive or detractor in submissions and adds a category column to the CSV export. NPS fields work with the numeric conditional-logic operators. `NpsFieldType`, `shared/nps.ts` and the React / Vue `NpsField`. ([#132](https://github.com/ArtisanPack-UI/forms/issues/132))
- Ranking field (`ranking`): visitors order the field's options (from the options editor, optionally randomized) with **Move up** / **Move down** buttons or by dragging. Focus stays on the moved choice and a live region announces its new position. The order is stored in `value_array` and must hold every option once (`size`, `in` and the new `forms_distinct` rule). Submissions show it numbered, and HTML views as an ordered list. `RankingFieldType`, the Livewire renderer's `moveRankingItem()` (dispatching `forms-ranking-moved`), `shared/ranking.ts` and the React / Vue `RankingField`. ([#133](https://github.com/ArtisanPack-UI/forms/issues/133))
- Image choices: a **Choice Display** setting on radio and checkbox groups (**Labels** or **Image choices**). With image choices, each option in the options editor takes an **Image URL**, alt text and an **Upload** button (JPEG, PNG, GIF or WebP, up to `uploads.choice_images.max_size` KB). Uploads are stored with the media library when it is installed, otherwise on the public `uploads.choice_images.disk`; the Livewire builder also offers the media library's picker. The renderers show each choice as a card with its image above the input and label, and the label stays the input's accessible name (`aria-labelledby`). Image URLs must be `http`/`https` or a site path; anything else is dropped when the options are read. `ArtisanPackUI\Forms\Support\ChoiceImages`, `FormField::showsImageChoices()`, the Livewire builder's `storeChoiceImage()`, and a `POST {prefix}/{form}/fields/choice-images` endpoint for the React and Vue builders, whose `post()` now sends a `FormData` body as multipart. ([#134](https://github.com/ArtisanPack-UI/forms/issues/134))
- Calculation field (`calculation`): a read-only number worked out from other fields with a **Formula** such as `{quantity} * {price}` (numbers, field merge tags, `+ - * /` and parentheses), rounded to its **Decimal Places** (default 2). The formula is read by a small parser, never `eval`. A merge tag reads a number, a list of numbers as their sum, a checked box as 1 and anything else as 0, and a calculation may use another's result. Dividing by zero, or a cycle, gives no result. The result updates as the visitor types in all three renderers, without the values of fields conditional logic hides, and the server works it out again on submit, ignoring the value sent. The builders' formula editor has an **Insert a field** picker that inserts a merge tag at the cursor and names the first problem with the formula. `ArtisanPackUI\Forms\Support\Calculation`, `SubmissionService::applyCalculations()`, the `formula` settings input, `calculation` on each field in the API, and the `shared/calculation.ts` helpers. ([#135](https://github.com/ArtisanPack-UI/forms/issues/135))
- Color field (`color`): the browser's color picker joined to a hex code input, checked with Laravel's `hex_color` rule. A typed `FF8800` or `#F80` is stored as `#ff8800`. Submissions, notifications and the submission details show a swatch beside the hex code, and the export has the hex code. Works with the equality and emptiness conditional-logic operators. ([#136](https://github.com/ArtisanPack-UI/forms/issues/136))

### Changed

- Headings without an alignment no longer get a `text-left` class, so alignment classes in a heading's CSS classes still apply.
- Conditional logic reads a list field's filled rows (a list of only blank rows is empty) and a date and time as `YYYY-MM-DD HH:MM`, the same way on the server and in every renderer.
- Paragraph text is read as HTML with basic formatting: `<strong>`, `<em>`, `<u>` and links are kept and other tags are removed, where before 1.6.0 all markup was shown as text. Text that looks like a tag, such as `<name@example.com>`, is removed too, so check existing paragraphs. A hidden section hides the fields it groups wherever hidden fields are worked out (`ConditionalLogicService::evaluateAllFields()` and `getHiddenFields()` in JS). ([#125](https://github.com/ArtisanPack-UI/forms/issues/125), [#126](https://github.com/ArtisanPack-UI/forms/issues/126))
- The Vue renderer's radio groups are native radios in a labelled `radiogroup`, as in the React renderer, so they can take the new choice layouts.
- `FormSubmission::$data` / `$data_array` hold a composite field's value as a map of its inputs rather than a joined string. Other values are unchanged.
- URL fields accept only `http` and `https` addresses unless their **Allowed Schemes** list others; the rule is now `url:http,https` instead of `url`, which also accepted `ftp:`, `javascript:` and other schemes. ([#120](https://github.com/ArtisanPack-UI/forms/issues/120))
- Date fields accept only `YYYY-MM-DD` dates (`date_format:Y-m-d`), as they are stored. The `date` rule alone also took forms such as `June 15 2015`, which skipped the disabled-day and age rules. ([#122](https://github.com/ArtisanPack-UI/forms/issues/122), [#123](https://github.com/ArtisanPack-UI/forms/issues/123))
- React and Vue check email and URL formats even when a field has no validation rules, as the server does. ([#119](https://github.com/ArtisanPack-UI/forms/issues/119), [#120](https://github.com/ArtisanPack-UI/forms/issues/120))

### Fixed

- The Livewire renderer no longer stores files uploaded to a file field that conditional logic hides. Hidden fields are not validated, so their files skipped the type and size checks; the submit API already dropped them. ([#114](https://github.com/ArtisanPack-UI/forms/issues/114))
- A custom layout type registered through `ap.forms.fieldTypes` with `is_layout => true` is skipped like the built-in layout types: it gets no validation rules, messages or input settings, and the Livewire notification editor no longer offers it as a condition field. ([#88](https://github.com/ArtisanPack-UI/forms/issues/88))
- A file field's **Max File Size** and **Allowed File Types** are now enforced on the server. All three builders save them to `field_config`, which the validation rules, the input's `accept` attribute and the size hint all read. Values saved in `validation_rules` by earlier versions still apply until the field is next saved. Allowed types are file extensions (such as `pdf`), and entries that are not plain extensions are ignored. ([#79](https://github.com/ArtisanPack-UI/forms/issues/79))
- The Livewire renderer validates file fields against the uploaded file (`files.{name}`) instead of `formData.{name}`. A required file field now passes once a file is uploaded, and an optional one has its type and size checked. ([#80](https://github.com/ArtisanPack-UI/forms/issues/80))
- A required single checkbox (or toggle) must now be checked. It uses the `accepted` rule, so checking and then unchecking it no longer passes. The client-side check in `validation.ts` matches. ([#81](https://github.com/ArtisanPack-UI/forms/issues/81))
- Heading, paragraph and HTML content now renders the same in every builder and renderer. A heading's text is its `label`; paragraph and HTML content is stored in `field_config.content`. Content saved to `default_value` by the React or Vue builder before this release still renders, and moves to the new place the next time the field is edited. ([#82](https://github.com/ArtisanPack-UI/forms/issues/82))
- The Livewire builder's min, max, step, max file size and allowed types inputs work on Livewire 4. Their `wire:change` expressions called `parseInt` and arrow functions, which Livewire 4 rewrites into server calls, so changing them caused a 500 error.
- Submission details, `{field}` merge tags, notification emails and the CSV export show the label of the option a person picked instead of its stored value, and a single checkbox shows "Yes" / "No" instead of `1`. The stored value is shown when the field or option has since been deleted. Notification conditions, webhooks and the JSON export still receive the stored values. ([#85](https://github.com/ArtisanPack-UI/forms/issues/85))
- A validation limit of `0` (min, max, min/max date and time) is now applied. Before, a Number field with a minimum of 0 accepted negative numbers. The client-side check in `validation.ts` matches. ([#86](https://github.com/ArtisanPack-UI/forms/issues/86))
- Time fields accept `H:i:s` as well as `H:i`, so a time with seconds (sent by browsers when the step is under 60) no longer fails validation, and min/max time compare correctly in both formats. A zero seconds part is dropped when the value is stored. ([#87](https://github.com/ArtisanPack-UI/forms/issues/87))
- The Livewire date field's text is vertically centred in its box.
- React radio fields render native radios, so the chosen option is checked and submitted. The `Radio` component spread the field's current value onto every option. ([#103](https://github.com/ArtisanPack-UI/forms/issues/103))
- The Livewire submission detail renders submissions with file uploads. Its download links passed only the upload to the `forms.uploads.download` route, which also needs the form and submission, so the page failed with a missing-parameter error.
- The Livewire, React and Vue submission details show a value's HTML (a list's table, a ranking's ordered list, a drawn signature) before treating it as an upload or a plain array, so a list no longer shows its rows as JSON in the Livewire detail.
- `FieldTypes::WIDTH_CLASSES` and `FieldTypes::getWidths()` return the responsive width classes (`w-full md:w-1/2`) the renderers use, instead of fixed widths that broke the mobile layout. `FormField::$width_class` and the React and Vue renderers read the same classes. ([#89](https://github.com/ArtisanPack-UI/forms/issues/89))
- Saving a form from the React `FormBuilder` no longer fails when its name has no Latin letters (`Привет мир`). An update that clears a form's slug now derives a new one from the name with `Str::slug` (`privet-mir`), or keeps the old slug when the name has nothing to slug, and the builder shows the slug the server chose. ([#138](https://github.com/ArtisanPack-UI/forms/issues/138))
- The React builder's client-side `slugify` handles punctuation like `Str::slug`: apostrophes and periods are removed (`Don't stop` gives `dont-stop`) and `@` becomes `at`, so a form whose slug the server made opens with its slug following the name. ([#139](https://github.com/ArtisanPack-UI/forms/issues/139))
- The React and Vue file inputs of a single-file field keep only the first file when several are dropped at once, instead of sending a list the server rejects. A list sent to a single-file field through Livewire or the API still fails validation rather than being dropped silently. Multi-file fields are covered by **Allow multiple files** ([#114](https://github.com/ArtisanPack-UI/forms/issues/114)). ([#146](https://github.com/ArtisanPack-UI/forms/issues/146))
- The time picker input sets `aria-invalid="true"` when the field has an error and takes the headline-cased field name as its `aria-label` when the field has no label, as the date picker does. The checkbox, file and radio group partials set `aria-invalid` too: on the input, on each box of a checkbox group (the `group` role does not take it) and on the `radiogroup`. An unlabelled file input gets the field name as its `aria-label`. ([#148](https://github.com/ArtisanPack-UI/forms/issues/148))

### Removed

- The `field_config.multiple` flag on `select` and `file` fields no longer renders a multi-value input, since validation and storage only handle one value. Use the Multi-Select field type for several choices. ([#84](https://github.com/ArtisanPack-UI/forms/issues/84))

### Security

- HTML block content is now sanitized with htmLawed's safe mode. This removes scripts, event handlers, `javascript:` URLs and embeds, along with inline `style` attributes and `<style>` elements. `kses()` with its default configuration had let these through. The API sanitizes `field_config.content` as well as `default_value`, because the React and Vue renderers insert this content as raw HTML.
- The Livewire renderer no longer stores the values of fields hidden by conditional logic, including every field in a hidden section. They weren't validated, so a visitor could set them through `$wire` and have them saved. The submit API already dropped them.
- The CSV export neutralises spreadsheet formulas (CSV injection, CWE-1236). A header or cell starting with `=`, `+`, `-`, `@`, a tab or a carriage return gets a leading `'`, after the `ap.forms.exportHeaders` and `ap.forms.exportData` filters run. Plain numbers such as `-12.5` and E.164 phone numbers such as `+15551234567` are left alone. The JSON export keeps raw values. See `ExportService::neutraliseFormula()`. ([#150](https://github.com/ArtisanPack-UI/forms/issues/150))
- The Livewire `FormRenderer` locks `formLoadedAt`, `currentStepIndex` and `hiddenFields` with `#[Locked]`, so the browser can't backdate the spam timer, jump past step validation or hide a required field. Steps change only through `nextStep()`, `previousStep()` and `goToStep()`, and the client no longer syncs `hiddenFields` back. The server works it out whenever the form data changes. The load time and the spam check now read `now()`, so tests can move the clock with `Carbon::setTestNow()`. ([#151](https://github.com/ArtisanPack-UI/forms/issues/151))
- Values sent for layout fields (section, heading, paragraph, divider and HTML) are no longer stored. They had no validation rules, so any string, or a nested array that broke the submission view, could be saved. `SubmissionService::create()` skips them, and the submit API drops them in `sanitizedData()`. ([#152](https://github.com/ArtisanPack-UI/forms/issues/152))

## [1.5.2] - 2026-10-18

### Added

- `FormRenderer` (React and Vue) accepts two optional props ([#141](https://github.com/ArtisanPack-UI/forms/issues/141)):
  - `layout` (`'stacked' | 'inline'`, default `'stacked'`). `inline` renders the submit button as the last item in the field row, aligned to the bottom of the fields and not growing, without its own wrapper `<div>`. Full-width fields shrink to a 12rem minimum (capped at the row width, so a narrow row never overflows) so the button can share their row, and the button wraps below them when the row is too narrow. A newsletter signup with one email field and a Subscribe button now sits on one line without CSS overrides. Multi-step forms ignore `layout`. The default stacked output is unchanged.
  - `submitClassName`, appended to the submit button's classes in both layouts so a host can apply its own design system (for example `wp-element-button`).
  - New `FormLayout` type in the TypeScript definitions, and a matching `layout` prop on `FieldRenderer`.

## [1.5.1] - 2026-10-16

### Fixed

- The admin form **Settings** tab could not be saved. It rendered `<form method="PUT">` without `@csrf` or `@method('PUT')`, so the browser sent a GET request and **Save Changes** threw `MethodNotAllowedHttpException`. The form now posts with a CSRF token and a spoofed `PUT`. The create form was also missing `@csrf`; it only worked on Laravel 13 because of the framework's origin-based request forgery check. ([#77](https://github.com/ArtisanPack-UI/forms/issues/77)) Thanks to [@aohlrogge](https://github.com/aohlrogge) for reporting this.
- The Settings checkboxes (**Form is active**, **Show progress bar**, **Allow step navigation**) now save in both directions. A ticked box used to submit `"on"`, which failed the `boolean` rule, and an unticked box was never submitted, so a setting could not be turned off. Each box now has a hidden `0` fallback and submits `1` when ticked, and an unticked box stays unticked when the form is re-rendered after a validation error. ([#77](https://github.com/ArtisanPack-UI/forms/issues/77))
- The edit page now opens on the Settings tab when the settings form has validation errors, so the errors are no longer hidden behind the Fields tab. ([#77](https://github.com/ArtisanPack-UI/forms/issues/77))
- The client-side `slugify()` used by the React form builder now converts Latin letters that Unicode normalization cannot break down (`æ`, `œ`, `ß`/`ẞ`, `ø`, `đ`, `ð`, `þ`, `ł`, `ħ`, `ı`, `ŋ`, `ĸ`, `ŧ`, `ƒ`, and Catalan `ŀ`) instead of dropping them. Accented forms such as `Ǽ` and `ǿ` are covered as well. For example, `Æther` now gives `aether` instead of `ther`, matching Laravel's `Str::slug`. ([#75](https://github.com/ArtisanPack-UI/forms/pull/75))
- The test suite's `FakeAgentPrompter` now accepts the optional `$tools` argument that `artisanpack-ui/ai` 1.2.0 added to `AgentPrompter::prompt()`. Previously the whole suite crashed with a fatal signature error on a fresh install that pulled in ai 1.2.

### Changed

- The **Enable multi-step form** checkbox was removed from the Settings tab. It changed `is_multi_step` without moving fields into or out of steps, which could leave the public form showing no fields. Use the **Multi-Step Form** toggle on the Fields tab instead, which converts the form properly. ([#77](https://github.com/ArtisanPack-UI/forms/issues/77))

### Upgrading

- If you published the package views (`forms-views`), republish them with `php artisan vendor:publish --tag=forms-views --force`, or apply the `forms/edit.blade.php` and `forms/create.blade.php` changes to your copies. Otherwise your published settings form will keep the broken behaviour.

## [1.5.0] - 2026-08-31

### Added

- Submission lifecycle columns and quarantine-aware notifications so a spam-quarantine integration has a first-class contract to write to and suppress on, complementing the existing `FormSubmitted` event and `ap.forms.submission.created` action hook ([#68](https://github.com/ArtisanPack-UI/forms/issues/68)):
  - Migration adds a nullable `spam_score` (decimal) column and a `status` column (default `received`), both indexed.
  - `SubmissionStatus` enum (`Received`, `Quarantined`, `Archived`) with a translatable `label()`.
  - `FormSubmission` gains the two columns as fillable/cast attributes, `quarantined()` and `withStatus()` query scopes, and `isQuarantined()` / `quarantine()` / `release()` helpers.
  - `NotificationService` suppresses the notification pipeline for quarantined submissions by default, overridable via the new `ap.forms.submission.should_send_notifications` filter. Payload: `(bool $shouldSend, FormSubmission $submission)`.
  - `FormSubmissionFactory` gains `quarantined()` and `archived()` states.
- React field-component registry seam so a host can render and edit a host-defined custom field type (e.g. `artisanpack-ui/bookings`' `booking_slot`) without patching vendored source — bringing the React renderer to parity with the server-side `ap.forms.*` extensibility ([#69](https://github.com/ArtisanPack-UI/forms/issues/69)):
  - `registerFieldComponent(type, component)` / `FormRenderer` + `FieldRenderer` `fieldComponents` prop — overlay the built-in renderer map so an unknown `field.type` resolves to a host component instead of rendering `null`.
  - `registerFieldPaletteGroup(group)` / `FieldPalette` `extraGroups` (and `FormBuilder` `paletteExtraGroups`) prop — append builder palette groups for custom types; palette items may carry an `iconPath` (raw SVG path) for an icon outside the built-in set.
  - `registerFieldSettings(type, component)` / `FieldEditor` `customSettings` (and `FormBuilder` `fieldCustomSettings`) prop — contribute a per-type settings panel to the editor's General tab. The panel receives `allFields` (the other fields in the form), mirroring the server-side `ap.forms.fieldSettings` filter's `Form` argument, so it can build controls that map to existing fields (e.g. a booking's name/email/phone selects).
  - `registerFieldCardPreview(type, component)` / `FormBuilder` `fieldCardPreviews` prop — render a live preview inside the field's card on the builder canvas, the React equivalent of the server-side `ap.forms.fieldCardPreview` filter.
  - Across all seams a per-instance prop takes precedence over a module-level registration; renderer components then fall back to the built-in (overlay by type), palette groups append after the built-ins, editor settings render additively below the shared settings, and card previews have no built-in fallback (the generic card shows when none is registered).
- Widened the `FieldType` union to `BuiltInFieldType | (string & {})` so a TypeScript host can legally construct and hold a custom field across `FormField.type`, `StoreFieldRequest.type`, `FieldPaletteItem.type`, and the admin editor props while keeping autocomplete for the built-in types. ([#69](https://github.com/ArtisanPack-UI/forms/issues/69))

### Fixed

- Bare `<button>` elements across the React, Vue, and Livewire admin surfaces fell through to the browser UA defaults — arrow cursor, no hover feedback, and no focus ring (WCAG 2.2 SC 2.4.7 Focus Visible). Each genuinely-bare button now carries the interaction affordance the daisyUI buttons already inherit, via per-button utility classes since the package ships no stylesheet: the React/Vue `SubmissionsList` view buttons gain `link-hover`; the Livewire sort headers (forms-list, submissions-list), field-card select, and notification select gain `cursor-pointer`, a hover state, and a focus-visible ring; the AI feature partials keep their consumer-styled BEM hook and gain neutral affordance utilities. daisyUI `.btn`/`.tab`/`.link` buttons, drag handles, and the `SubmissionDetail` anchors were already correct and left untouched. ([#70](https://github.com/ArtisanPack-UI/forms/issues/70))

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
