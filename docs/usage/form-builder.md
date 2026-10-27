---
title: Form Builder
---

# Form Builder

The Form Builder provides a drag-and-drop interface for creating and editing forms without writing code.

## Accessing the Form Builder

Navigate to `/admin/forms/create` to create a new form, or `/admin/forms/{form}/edit` to edit an existing form.

## Form Settings

### Basic Settings

| Setting | Description |
|---------|-------------|
| **Name** | Internal name for the form |
| **Slug** | URL-friendly identifier (auto-generated from name) |
| **Description** | Optional description for admin reference |
| **Active** | Whether the form accepts submissions |

### Submission Settings

| Setting | Description |
|---------|-------------|
| **Success Message** | Message shown after successful submission |
| **Redirect URL** | Optional URL to redirect after submission |
| **Store Submissions** | Whether to save submissions to database |

### Display Settings

| Setting | Description |
|---------|-------------|
| **Label Position** | Above, beside, or hidden labels |
| **Show Required Indicator** | Show asterisk for required fields |
| **Submit Button Text** | Text for the submit button |

## Adding Fields

1. Click "Add Field" or drag a field type from the toolbar
2. Configure field settings in the sidebar
3. Drag fields to reorder
4. Click "Save" to save the form

## Field Configuration

Each field has common and type-specific settings:

### Common Settings

| Setting | Description |
|---------|-------------|
| **Label** | Field label displayed to users |
| **Name** | Field identifier (used in submissions) |
| **Placeholder** | Placeholder text |
| **Help Text** | Helper text below the field |
| **Required** | Whether the field is required |
| **CSS Classes** | Custom CSS classes |

### Validation Settings

| Setting | Description |
|---------|-------------|
| **Min Length** | Minimum character length |
| **Max Length** | Maximum character length |
| **Pattern** | Regular expression pattern |
| **Custom Error** | Custom validation error message |

## Field Types

### Text Inputs

```php
// Text field configuration
[
    'type' => 'text',
    'name' => 'full_name',
    'label' => 'Full Name',
    'required' => true,
    'validation' => [
        'min' => 2,
        'max' => 100,
    ],
]

// Email field
[
    'type' => 'email',
    'name' => 'email',
    'label' => 'Email Address',
    'required' => true,
]

// Textarea
[
    'type' => 'textarea',
    'name' => 'message',
    'label' => 'Your Message',
    'rows' => 5,
]
```

### Input Masks

Text and phone fields have an **Input Mask** setting that formats the value as it is typed and checks it on submit. Choose a preset or **Custom**:

| Preset | Mask | Example |
|--------|------|---------|
| US Phone | `(999) 999-9999` | `(555) 555-0123` |
| US ZIP Code | `99999` | `12345` |
| US ZIP+4 | `99999-9999` | `12345-6789` |
| Social Security Number | `999-99-9999` | `123-45-6789` |
| Date | `99/99/9999` | `10/24/2026` |

A **Custom Mask** uses `9` for a digit, `a` for a letter and `*` for a letter or digit; every other character is typed for the visitor (for example `999-aa-***`). The value is stored with those characters.

The expected format is shown under the input (`mask-{name}`, referenced by its `aria-describedby`), and the server rejects a value that does not match the whole mask with a `regex` rule, so the custom error message applies. The Livewire renderer uses Alpine's mask plugin (bundled with Livewire); React and Vue use `resources/js/shared/mask.ts`, which follows the same rules. The mask only changes the input's text, so screen readers announce the typed value as usual.

```php
FormField::create( [
    'form_id'      => $form->id,
    'type'         => 'text',
    'name'         => 'member_id',
    'label'        => 'Member ID',
    'field_config' => ['input_mask' => 'custom', 'custom_mask' => 'aa-9999'],
] );
```

### Character and Word Counters

Text and textarea fields set `minlength` and `maxlength` on the input from their **Min Length** and **Max Length** rules, so browsers stop typing at the limit. **Show character counter** adds a live counter under the input, such as "42 of 280 characters" ("42 characters" without a max).

Textareas have a **Max Words** rule (`validation_rules.max_words`, checked on the server by the `forms_max_words` rule and in `validation.ts`). With a word limit the counter counts words instead: "120 of 250 words". Words are runs of characters between whitespace.

The counter is referenced by the input's `aria-describedby` (`counter-{name}`). A separate polite live region repeats the count once typing pauses for a second, so screen readers are not interrupted on every keystroke.

### Phone Formats

A phone field's **Phone Format** decides how numbers are checked:

| Format | Validation |
|--------|------------|
| **Any** (default) | Any text, plus the optional pattern and input mask. |
| **US** | A valid US number. |
| **International** | A valid number in any country, with a country selector that starts at the **Default Country**. |

US and International numbers are checked with [`propaganistas/laravel-phone`](https://github.com/Propaganistas/Laravel-Phone) (libphonenumber), stored in E.164 form (`+15555550123`) so notifications, webhooks and exports get one consistent value, and shown formatted in submissions (national form for US, international otherwise).

With International, a number typed without a `+` country code is read in the selected country. The Livewire renderer submits the selected country as `{name}_country`, which the `phone` rule reads and which is not stored. React and Vue add the country's calling code to the number instead (`+44 20 7946 0958`). The countries come from the `ap.forms.countries` list (see Address); the API returns them with their calling codes in each field's `phone.countries`.

```php
FormField::create( [
    'form_id'      => $form->id,
    'type'         => 'phone',
    'name'         => 'mobile',
    'label'        => 'Mobile',
    'field_config' => ['phone_format' => 'international', 'default_country' => 'GB'],
] );
```

### Number Options

Number fields have three more settings:

- **Number Format**: a plain number input (default), `9,999.99`, `9.999,99` or **Currency** (`9,999.99`, rounded to two decimal places). With a format the input is a text input with a decimal keypad, so visitors can type thousands separators. Spaces and symbols are ignored, and the value is converted to a plain decimal (`1234.56`) before validation, so Min Value, Max Value and Step apply, and stored that way.
- **Whole numbers only** (`validation_rules.integer_only`): adds the `multiple_of:1` rule, separate from the step increment. Unlike `integer`, it accepts a whole number written with zero decimals, such as the `3.00` a Currency format stores.
- **Prefix** and **Suffix**: text shown before and after the input, such as `$` or `kg`. Screen readers get them from a description (`affix-{name}`, "Prefix: $. Suffix: USD.") referenced by the input's `aria-describedby`, since the visible text is decoration.

```php
FormField::create( [
    'form_id'          => $form->id,
    'type'             => 'number',
    'name'             => 'budget',
    'label'            => 'Budget',
    'field_config'     => ['number_format' => 'currency', 'prefix' => '$'],
    'validation_rules' => ['min' => 100],
] );
```

### Email Validation

Email fields have three Validation tab settings:

- **Email Validation** (`validation_rules.email_validation`): **Standard (RFC)** (default, Laravel's `email` rule), **Standard and the domain must exist (DNS)** (`email:rfc,dns`, which looks up the domain's MX or A records on each submission) or **Strict** (`email:strict`, RFC without the warnings RFC tolerates). The DNS check runs on the server only.
- **Allowed Domains** (`validation_rules.allowed_domains`): only addresses at these domains are accepted.
- **Blocked Domains** (`validation_rules.blocked_domains`): addresses at these domains are rejected, such as free providers on a business form.

A listed domain also covers its subdomains, so `example.com` matches `jo@mail.example.com` but not `jo@notexample.com`. Entries may start with `@` and are compared case-insensitively. Both lists are checked by the `forms_email_domain` rule ("Please use an email address from an accepted domain for …"), so the custom error message applies, and by `validation.ts` in React and Vue.

```php
FormField::create( [
    'form_id'          => $form->id,
    'type'             => 'email',
    'name'             => 'work_email',
    'label'            => 'Work Email',
    'validation_rules' => [
        'email_validation' => 'dns',
        'blocked_domains'  => ['gmail.com', 'yahoo.com', 'hotmail.com'],
    ],
] );
```

### URL Schemes

URL fields accept only `http` and `https` addresses unless **Allowed Schemes** (`validation_rules.allowed_schemes`) lists others, such as `https` alone or `https, ftp`. The rule is `url:{schemes}`, so `javascript:` and other schemes are rejected. Laravel's `url` rule needs `://` after the scheme, so schemes such as `mailto:` and `tel:` cannot be allowed.

Visitors often type `example.com`, which has no scheme. Before validation the server adds `https://` (or `http://` when only `http` is allowed), so `example.com/about` is validated and stored as `https://example.com/about`. A protocol-relative `//example.com` gets the scheme alone, and `localhost:8080` reads as a host and port. All three renderers add the scheme when the input loses focus, so visitors see the address that will be stored.

### Rich Text

A textarea's **Use rich text editor** toggle (`field_config.rich_text`) replaces the textarea with a small editor: a toolbar with **Bold**, **Italic**, **Underline**, **Bulleted list**, **Numbered list** and **Link** above an editable area. The field then submits HTML.

- **Sanitized**: the HTML is cleaned with `kses()` (artisanpack-ui/security) when it is stored and again when it is shown. Only paragraphs, line breaks, bold, italic, underline, strikethrough, lists, quotes and links survive, links keep only `href` (http, https and mailto) and get `rel="nofollow"`, and every other element and attribute is removed. See `ArtisanPackUI\Forms\Support\RichText`.
- **Limits count text**: Min Length, Max Length and Max Words count the text, not the markup (the `forms_min_text`, `forms_max_text` and `forms_max_words:{max},html` rules), and so does the character counter. An emptied editor (`<p><br></p>`) counts as empty, so a required field fails. The markup itself is capped at 65,535 characters (`RichText::MAX_HTML_LENGTH`), so empty tags cannot pad a value past the limits. Default, populated and read-only values are sanitized before an editor shows them.
- **Display**: `display_value` is the plain text (list items start with `- `), used in text emails, CSV exports, merge tags and the submissions list. HTML views use `FormSubmissionValue::$display_html` (`display_html` in the API): the HTML notification table and the Livewire, React and Vue submission views show the sanitized HTML.
- **Accessibility**: the toolbar is a `role="toolbar"` labelled "Formatting for {field}", with one button in the tab order at a time. The arrow keys, Home and End move between buttons, and each button reports its state with `aria-pressed`. The editor is a `role="textbox"` with `aria-multiline`, labelled by the field label and described by its hint and error. Ctrl/Cmd+B, I and U work in the editor.

The Livewire renderer does not use the `livewire-ui-components` Editor (TinyMCE), since that loads TinyMCE from a CDN and uploads images to an authenticated route, which public visitors cannot use.

### Selection Fields

```php
// Select dropdown
[
    'type' => 'select',
    'name' => 'country',
    'label' => 'Country',
    'options' => [
        ['value' => 'us', 'label' => 'United States'],
        ['value' => 'ca', 'label' => 'Canada'],
        ['value' => 'uk', 'label' => 'United Kingdom'],
    ],
]

// Radio buttons
[
    'type' => 'radio',
    'name' => 'contact_method',
    'label' => 'Preferred Contact Method',
    'options' => [
        ['value' => 'email', 'label' => 'Email'],
        ['value' => 'phone', 'label' => 'Phone'],
    ],
]

// Checkbox group
[
    'type' => 'checkbox_group',
    'name' => 'interests',
    'label' => 'Interests',
    'options' => [
        ['value' => 'news', 'label' => 'Newsletter'],
        ['value' => 'updates', 'label' => 'Product Updates'],
        ['value' => 'offers', 'label' => 'Special Offers'],
    ],
]
```

The server only accepts a selection field's own option values (the values a choices source resolved, for a field that loads them from one). Each value of a checkbox group or multi-select must also be a string, so nested arrays are rejected. A field that offers an **Other** choice accepts any text, since that is stored as the value.

### Date and Time

Dates are always stored as `YYYY-MM-DD` and times as 24-hour `HH:MM` (`HH:MM:SS` with a step under a minute), whichever input the field uses.

**Date input** (General tab):

- **Input Type** (`field_config.date_input`): the native **Date picker** (default), **Month, day and year dropdowns** or **Month, day and year fields** (number inputs). Separate inputs are easier than a calendar for birthdates. They are grouped in a `<fieldset>` whose `<legend>` is the field label, each with its own label. With the `bday` autocomplete token they get `bday-month`, `bday-day` and `bday-year`. An incomplete or impossible date, such as 31 February, fails the `date` rule.
- **Date Format** (`field_config.date_format`): `YYYY-MM-DD` (default), `MM/DD/YYYY` or `DD/MM/YYYY`. It orders the separate inputs and formats stored dates in submissions, notifications and exports. The native picker follows the browser's locale.

**Date limits** (Validation tab):

- **Min Date** and **Max Date** take an absolute date (`2026-12-31`), `today`, or a signed number of days, weeks, months or years from today, such as `+30 days` or `-1 year`. Relative limits are resolved each day (months and years do not overflow, so a month after 31 January is the end of February), on the server in the application's timezone and in React and Vue in the visitor's.
- **Allowed Dates** (`validation_rules.date_range`): **No past dates** starts the range today; **No future dates** ends it today. The stricter of this and Min / Max Date applies.
- **Disable weekends** and **Disabled Dates** (`YYYY-MM-DD`, comma-separated) reject those days with the `forms_disabled_dates` rule. The native picker cannot grey them out, so the error explains it.
- **Minimum Age** and **Maximum Age** check a birthdate (`forms_min_age`, `forms_max_age`): someone aged 18 today may submit with a minimum age of 18.

Dates must be `YYYY-MM-DD` (`date_format:Y-m-d`), as they are stored, so a date written another way, such as `June 15 2015`, cannot skip the disabled-day and age checks, and a year typed as `90` in the fields mode is rejected rather than stored as year 90. All three renderers submit dates in that form.

The native `min` and `max` attributes and the year dropdown follow the resolved limits and age limits (`FormField::getDateBounds()`), in all three renderers.

**Time input** (General tab):

- **Input Type** (`field_config.time_input`): the native **Time picker** (default) or **Hour and minute dropdowns**, with an AM/PM dropdown on a 12-hour clock, grouped in a fieldset like the date inputs. The minute dropdown follows the step: a 900-second step offers 00, 15, 30 and 45.
- **Time Format** (`field_config.time_format`): **24-hour** (default) or **12-hour**, for the dropdowns and for stored times in submissions, notifications and exports ("2:30 PM").

```php
FormField::create( [
    'form_id'          => $form->id,
    'type'             => 'date',
    'name'             => 'birth_date',
    'label'            => 'Date of Birth',
    'field_config'     => ['date_input' => 'dropdowns', 'date_format' => 'mdy', 'autocomplete' => 'bday'],
    'validation_rules' => ['date_range' => 'no_future', 'min_age' => 18],
] );

FormField::create( [
    'form_id'          => $form->id,
    'type'             => 'date',
    'name'             => 'appointment',
    'label'            => 'Appointment',
    'validation_rules' => ['min_date' => '+1 day', 'max_date' => '+3 months', 'disable_weekends' => true],
] );

FormField::create( [
    'form_id'      => $form->id,
    'type'         => 'time',
    'name'         => 'preferred_time',
    'label'        => 'Preferred Time',
    'field_config' => ['time_input' => 'dropdowns', 'time_format' => '12', 'step' => 900],
] );
```

#### Date & Time

A **Date & Time** field (`datetime`) asks for one date and time, such as an appointment, with the native `datetime-local` picker. Browsers submit `YYYY-MM-DDTHH:MM`; the value is checked (`date_format:Y-m-d H:i`) and stored as `YYYY-MM-DD HH:MM`, and a default value may use either form.

- **Date Format** and **Time Format** format the stored value in submissions, notifications and exports ("01/11/2026 2:30 PM"). The picker itself follows the browser's locale.
- **Min Date** and **Max Date** take the same absolute or relative dates as a date field. They bound the day: the value must be on or after the start of the min date and on or before the end of the max date. The picker's `min` and `max` follow them.
- **Step (seconds)** sets the picker's interval, and **Read-only** locks the value.

The value is the visitor's local date and time, stored without a timezone. Relative limits such as `today` resolve in the app's timezone on the server and the visitor's in React and Vue, so around midnight the two can differ by a day.

#### Date Range

A **Date Range** field (`date_range`) asks for a start and an end date, such as a booking's arrival and departure. It is a composite of two date inputs, **Start Date** and **End Date**, in a fieldset whose legend is the field label; the **Inputs** table relabels them, and both are required when the field is.

- Both dates are checked as `YYYY-MM-DD`, and the `forms_date_range` rule requires both once either is filled in, with the end on or after the start ("Please enter both dates for Stay, with the end date on or after the start date.").
- **Min Date** and **Max Date** (absolute or relative) limit both dates.
- **Date Format** formats the range in submissions: "11/01/2026 – 11/05/2026".

The value is stored in `value_array` as `['start' => '2026-11-01', 'end' => '2026-11-05']`, with `{stay:start}` and `{stay:end}` merge tags and one export column per date (or one formatted column).

```php
FormField::create( [
    'form_id'          => $form->id,
    'type'             => 'date_range',
    'name'             => 'stay',
    'label'            => 'Stay',
    'is_required'      => true,
    'field_config'     => ['date_format' => 'mdy'],
    'validation_rules' => ['min_date' => 'today', 'max_date' => '+1 year'],
] );

FormField::create( [
    'form_id'          => $form->id,
    'type'             => 'datetime',
    'name'             => 'appointment',
    'label'            => 'Appointment',
    'field_config'     => ['time_format' => '12', 'step' => 900],
    'validation_rules' => ['min_date' => '+1 day'],
] );
```

### File Upload

```php
[
    'type' => 'file',
    'name' => 'resume',
    'label' => 'Upload Resume',
    'accept' => '.pdf,.doc,.docx',
    'max_size' => 5120, // KB
    'multiple' => false,
]
```

**Allow multiple files** turns the field into a drop zone around a normal, keyboard-operable file input. The validation tab adds:

| Setting | Description |
|---------|-------------|
| **Max Files** (`max_files`) | The most files the field takes. Blank for no limit. |
| **Max File Size** (`max_size`) | The limit for each file, in KB. |
| **Max Total Size** (`max_total_size`) | The limit for all files together, in KB. |
| **Min / Max Image Width and Height** (`min_width`, `max_width`, `min_height`, `max_height`) | Pixel bounds for images (single or multiple files). Other allowed files are not checked. |

In the Livewire renderer each file uploads as soon as it is added, with its own progress bar and a remove button; a polite live region announces when each upload starts and finishes. React and Vue list the files with their sizes and remove buttons, and send them with the submission (`files[{name}][]`).

Each file is stored as its own `form_uploads` row. The field's submission value points at the first upload in `upload_id` and lists every upload ID, in order, in `value_array`; `FormSubmissionValue::getUploads()` returns them, and the API returns `upload_ids`. Submissions, notifications and exports list every file name. See [File Uploads](file-uploads.md).

The note under the input lists the field's limits: the number of files, the **Allowed File Types** (as `PDF, DOCX`) and the size limits, such as "Allowed file types: PDF, DOCX · Maximum file size: 5120 KB". The input's `aria-describedby` references it in all three renderers.

**Storage** (Advanced tab) keeps uploads on the private forms disk (default) or, with **Media Library**, saves each file as an `artisanpack-ui/media-library` item, titled with the file's name. The upload's `form_uploads` row then points at the media item's file (`media_id`, `disk` and `path`; run the migrations), and deleting the upload, or pruning its submission, deletes the media item and its files. When the media item cannot be deleted, its original file is still removed and a warning naming the media item is logged.

**Visibility** and the privacy settings (**Do not store**, **Mask in admin** and **Redact after (days)**) are in the Advanced tab, as for other inputs. An administrative file field is filled in on the submission detail, where admins upload, replace and remove its files. A "Do not store" field's files are attached to notifications and sent in webhook payloads, then deleted once those have run. A masked field's file names, previews and downloads are hidden from users who may not reveal masked values, and redaction deletes the stored files. See [File Uploads](file-uploads.md#visibility-and-privacy).

Media library files follow the media library disk's visibility, which is **public** by default, so only use this storage for files that may be public, or point the media library at a private disk. Set `uploads.media_library.folder_id` (`FORMS_MEDIA_FOLDER_ID`) to file the uploads in a media folder, and `uploads.media_library.uploaded_by` (`FORMS_MEDIA_UPLOADED_BY`) to attribute guests' uploads to a user; otherwise the media library's own guest upload settings apply. When the media library is not installed, or rejects a file (for example because guest uploads are off or the file's type is not allowed there), the file is stored on the forms disk and a warning is logged.

### Name

A Name field collects a person's name in one of three **Name Format** presets:

| Format | Inputs |
|--------|--------|
| **Simple** | One `full` input (`autocomplete="name"`). The most inclusive choice, since many names do not split into first and last. |
| **Normal** (default) | First and Last shown; Prefix, Middle and Suffix available but off until you turn them on. |
| **Extended** | Prefix, First, Middle, Last and Suffix, all shown. |

Each part has its own `autocomplete` token (`honorific-prefix`, `given-name`, `additional-name`, `family-name`, `honorific-suffix`). In the **Inputs** table you can show or hide each part, change its sub-label and placeholder, give it a default value, set a population **Parameter** (filled from the renderer `prefill`, the query string, then the `ap.forms.fieldValue.{parameter}` filter) and mark it required. When a required field marks no part, every shown part is required.

The prefix is a select of **Prefix Choices** (default: Mr., Mrs., Ms., Mx., Dr., Prof., Rev.). The suffix is free text, or a select of **Suffix Choices** when **Suffix Input** is set to a list.

```php
FormField::create( [
    'form_id'      => $form->id,
    'type'         => 'name',
    'name'         => 'name',
    'label'        => 'Your Name',
    'is_required'  => true,
    'field_config' => [
        'name_format'    => 'extended',
        'prefix_choices' => ['Dr.', 'Prof.'],
        'sub_inputs'     => ['middle' => ['enabled' => false]],
    ],
] );
```

The value is stored as a map in `value_array` (`['first' => 'Jane', 'last' => 'Doe']`) and shown as the filled parts joined with spaces ("Dr. Jane Doe"). Merge tags: `{name}` for the full name and `{name:first}`, `{name:last}` and so on for each shown part. Exports get one column per shown part, or one formatted column with **Export Format** set to a single column.

### Address

An Address field collects Street Address, Address Line 2, City, State / Province / Region, ZIP / Postal Code and Country (`address-line1`, `address-line2`, `address-level2`, `address-level1`, `postal-code`, `country-name`). Each part can be shown or hidden, relabelled and required in the **Inputs** table.

| Setting | Description |
|---------|-------------|
| **Address Type** | **International** (free-text state, any postal code, country select), **United States** (state select, ZIP code such as `12345` or `12345-6789`, country fixed to the US) or **Canada** (province select, postal code such as `K1A 0B1`, stored in upper case, country fixed to Canada). |
| **Default Country** | The country selected to start with. |
| **Hide country** | International addresses only: stores the default country without showing the input. |
| **Default State / Province** | For U.S. and Canadian addresses, use the two-letter code (`NY`, `ON`). |

The country list comes from the `ap.forms.countries` filter (ISO 3166-1 alpha-2 code => name), which the **Countries** predefined choice list shares:

```php
addFilter( 'ap.forms.countries', function ( array $countries ): array {
    $countries['XK'] = __( 'Kosovo' );

    return $countries;
} );
```

The value is stored as a map in `value_array`, including a fixed country, and shown as a multi-line postal address:

```text
1 Main St
Albany, NY 12207
United States
```

Merge tags are `{address}` and one per part, such as `{address:city}` and `{address:postal_code}`.

**Address autocomplete providers.** Each input carries `data-forms-part="{key}"` and the fieldset `data-forms-composite="address"`, so a provider script (for example the `artisanpack-ui/bing-places` package) can find them. In the Livewire renderer, the `ap.forms.addressAutocomplete` filter's HTML is printed after the field:

```php
addFilter( 'ap.forms.addressAutocomplete', function ( string $html, FormField $field ): string {
    return $html . view( 'my-places::address-autocomplete', ['field' => $field] )->render();
} );
```

A provider that fills the inputs should set their values and dispatch an `input` event, so Livewire and Vue pick up the change. React inputs need the native value setter before the event.

### List

A **List** field (`list`) collects rows of one or more columns that visitors add with **Add …**, such as attendees, references or line items.

**Columns** (General tab) lists one column per line, as `Label | type | options`:

```text
Name
Email | email
Role | select | Speaker, Guest
Age | number
```

`type` is `text` (the default), `email`, `number` or `select`; a select's options are comma-separated. Each column's key is its label's slug (`name`, `email`, `role`, `age`), so renaming a column changes its key. Up to 10 columns.

| Setting | Description |
|---------|-------------|
| **Row Label** | Names one row in the add and remove buttons: with "Attendee", "Add Attendee" and "Remove Attendee 2". Default "Row". |
| **Min Rows** | The fewest filled rows, once any row is filled in (or always, for a required field). The list starts with this many empty rows. |
| **Max Rows** | The most rows (default and limit 100). The add button is disabled at the limit. |
| **Export Format** | One row per line in the CSV cell (default), or JSON objects keyed by column label. |

Rows left blank are ignored. A required list needs one filled row, and each cell is checked by its column's type (`email`, `numeric`, a select's options, up to 255 characters), with errors on the row the visitor sees. The value is stored in `value_array` as a list of rows (`[['name' => 'Ada', 'email' => 'ada@example.com']]`); submissions and HTML notifications show it as a table, and text emails, merge tags and the default export as one row per line ("Ada, ada@example.com").

Each row is a group named for its row ("Attendee 2"), with a labelled input per column; the column labels show as a header row on wider screens, where the inputs' own labels stay for screen readers. After a row is added its first input takes focus, after one is removed the add button does, and a polite live region announces each change. In the Livewire renderer the buttons call `addListRow( name )` and `removeListRow( name, index )`.

### Survey Fields

Four advanced fields cover common survey questions. All three renderers and builders support them.

#### Slider

A **Slider** (`slider`) picks a number on a native range input (`<input type="range">`). The chosen value is shown beside the track.

| Setting | Description |
|---------|-------------|
| **Minimum** / **Maximum** | The ends of the range (default 0 and 100). A maximum at or below the minimum is moved one step above it. |
| **Step** | The increment (default 1). Decimal steps such as `0.5` work. |
| **Hide value** | Leaves out the value shown beside the slider. Screen readers still announce it. |
| **Value Prefix** / **Value Suffix** | Added to the value label, such as `$` or `%`. A word such as `years` gets a space ("3 years"). |

A slider always has a value. It starts at the **Default Value** when that is within the range, or else at the minimum. The value is validated as `numeric|between:{min},{max}` plus `forms_step:{min},{step}`, which accepts only whole steps from the minimum (with floating-point tolerance, so `0.3` is on a `0.1` step). It is stored as a plain number. Submissions show it with its prefix and suffix ("$40"), exports use the number, and the numeric conditional-logic operators apply. The input's `aria-valuetext` reads the value label, so screen readers announce "$40" rather than "40".

#### Net Promoter Score

A **Net Promoter Score** field (`nps`) asks for a score from 0 to 10, shown as a row of buttons with **Low Label** (default "Not likely") under 0 and **High Label** (default "Extremely likely") under 10.

The scores are a native radio group: each radio is visually hidden inside its button, so arrow keys move between scores and the focus ring shows on the button. The 0 and 10 radios are named with their end labels ("0, Not likely"). The value is validated as `integer|between:0,10`; 0 is a valid score. With **Show category** on, submissions show the score's category ("9 – Promoter": promoters 9–10, passives 7–8, detractors 0–6) and the CSV export adds a "{label} (Category)" column. NPS fields work with the numeric conditional-logic operators.

#### Likert / Matrix

A **Likert / Matrix** field (`matrix`) is a grid of statements (rows) against a scale (columns), with one answer per row.

| Setting | Description |
|---------|-------------|
| **Layout** | **Matrix** (a row per statement, the default) or **Single scale**: one radio group labelled by the field label. A matrix with no rows is a single scale too. |
| **Rows** | One statement per line, up to 30. Each row's key is its statement's slug (`the_service_was_fast`), starting with `row_` when the slug is empty or a number. Renaming a statement changes its key. |
| **Columns** | One choice per line, as `Label` or `Label \| value`, up to 11. Defaults to a five-point agreement scale (Strongly disagree … Strongly agree). |

```text
Strongly disagree | 1
Disagree | 2
Neutral | 3
Agree | 4
Strongly agree | 5
```

Visitors submit a map of row keys to column values (`data[{name}][{row}]`). It is stored in `value_array` for the rows that were answered. Each row keeps its statement and answer label as they read when the form was submitted, beside the column value, so stored answers still read correctly after the rows or columns change:

```php
[
    'the_service_was_fast' => [
        'statement' => 'The service was fast',
        'answer'    => 'Agree',
        'value'     => '4',
    ],
]
```
 A required matrix needs every row answered, and each answer must be one of the columns. Errors land on the row (`formData.{name}.{row}`, `data.{name}.{row}` in the API) and show under its statement. A single scale's value is the chosen column value, stored as text.

Submissions, text emails and HTML notifications show one row per line ("The service was fast: Agree"). The CSV export has one column per row, headed "{label}: {statement}". Webhooks, the API and the JSON export receive the map.

The grid is a table: each column label is a column header and each statement is a row header. Every radio is named by its row and column ("The service was fast Agree") through `aria-labelledby`, and each row's radios share a name, so arrow keys move along the row. The table scrolls sideways on narrow screens.

#### Ranking

A **Ranking** field (`ranking`) asks visitors to put its choices in order of preference. The choices come from the options editor. Options have no "Selected by default" checkbox here, since every choice is ranked. **Randomize choice order** starts each visitor's list in a different order.

The list starts in the options' order, and the value is every option value, most preferred first, stored in `value_array` (`['quality', 'price', 'service']`). It must hold every option exactly once: the rules are `array|size:{count}|in:…` plus `forms_distinct`, which rejects repeats. Submissions and exports show the choices numbered ("1. Quality, 2. Price, 3. Service"), and HTML views show an ordered list.

Each choice has **Move up** and **Move down** buttons ("Move Price up") and can be dragged with a mouse. After a move, focus stays on the moved choice's button and a polite live region announces its new position ("Price moved to position 2 of 3."). In the Livewire renderer the buttons call `moveRankingItem( name, from, to )`, which dispatches `forms-ranking-moved`.

### Signature

A **Signature** field (`signature`) captures a signature for waivers and agreements. Visitors draw on a canvas pad with a mouse, finger or stylus (**Clear signature** starts again), or choose **Type your name** and type their full name instead. Typing is the accessible alternative for anyone who can't draw. **Pad Height** sets the pad's height: 120, 160 (default), 200 or 240 pixels.

A drawing is submitted as a PNG data URL (`data:image/png;base64,…`) and stored as a `form_uploads` row named `signature.png` on the forms uploads disk. The submission value points at it with `upload_id`. A typed name is stored as text. The `forms_signature` rule accepts either form: a PNG of at most 512 KB and 4000 pixels a side, or a name of up to 255 characters without control characters.

Submissions show a drawn signature as an image, and the PNG is also listed under **Attachments**. HTML notifications embed the image as an inline (`cid:`) attachment, so it shows in email clients that block data URLs. Text emails and exports read "Signed (drawn)", and a typed signature reads `Signed as "Ada Lovelace" (typed)`. The **Is empty** and **Is not empty** conditional-logic operators apply.

Signature fields have no privacy settings and can't be administrative, since the image is a stored file. In the Livewire renderer the pad is an Alpine canvas rather than `x-artisanpack-signature`, which needs the global `signature_pad` library and has no typed alternative. React and Vue use the shared `SignaturePad` class.

### Email Confirmation

Turn on **Enable email confirmation** on an email field to ask for the address twice. The field shows two inputs labelled by **Email Sub-Label** (default "Enter Email") and **Confirm Sub-Label** (default "Confirm Email"), with an optional **Confirm Placeholder**. The confirmation is submitted beside the field as `{name}_confirmation` (`data[email_confirmation]` in the API) and checked with Laravel's `confirmed` rule, so a mismatch shows as the email field's error. Only the email is stored; display, notifications and exports are unchanged. Pasting into the confirm input is never blocked, which matters for password-manager and assistive-technology users. Avoid naming another field `{name}_confirmation`.

### Consent

A Consent field is a checkbox for GDPR or terms acceptance that records what was agreed to.

| Setting | Description |
|---------|-------------|
| **Checkbox Text** | The text beside the checkbox (default "I agree to the privacy policy."). |
| **Consent Description** | Shown below the checkbox. Links and basic formatting are allowed; the HTML is sanitized with kses. |

A required consent field must be checked (`accepted`), even if the visitor checks and then unchecks it. A checked consent is stored in `value_array` as:

```php
[
    'consented'    => '1',
    'text'         => 'I agree to the privacy policy.',
    'description'  => 'Read our <a href="/privacy">privacy policy</a>.',
    'revision'     => '9f2c…', // SHA-256 of the text and description
    'consented_at' => '2026-10-24T12:00:00+00:00',
]
```

The `revision` hash changes whenever the text or description does, so you can tell which version each visitor agreed to. Set `privacy.consent.store_text` to `false` (`FORMS_CONSENT_STORE_TEXT=false`) to keep only the hash and time. Submissions show "Consented" with a **View consent text** disclosure; webhooks, the API and the JSON export receive the stored object. An unchecked optional consent stores `['consented' => '0']` and shows "Not consented".

### CAPTCHA

A CAPTCHA field adds a challenge where you place it, for example on the last step of a multi-step form. Pick a **Provider** (or the default from config), a **Widget Size** (normal, compact or invisible) and a **Theme**. See [Spam Protection](Spam-Protection#captcha) for keys, providers and accessibility notes. The token is checked on submit and never stored.

### Layout Elements

Layout elements show content and collect nothing.

**Section** (`section`) groups the fields after it, up to the next section or the end of the step, in a `<fieldset>` whose `<legend>` is the section's title (its label), so assistive technology announces the group. Its **Description** shows under the title. Conditional logic on a section shows or hides every field in it with one rule; a field in a shown section can still be hidden by its own logic. A CAPTCHA in a hidden section stays shown, since conditional logic never hides one. With **Collapsible** on, the title is a disclosure button (`aria-expanded`) that shows and hides the fields; **Start collapsed** starts it closed, and a section opens when one of its fields has an error.

**Heading** (`heading`) has a **Heading Level** (H2–H6) and an **Alignment** (left, center or right).

**Paragraph** (`paragraph`) keeps basic formatting: `<strong>`/`<b>`, `<em>`/`<i>`, `<u>` and links to `http`, `https` and `mailto` addresses (marked `rel="nofollow"`). Everything else is removed with `kses()`, and line breaks are kept. The render API sends the result as `content_html`.

**HTML** (`html`) is sanitized with htmLawed's safe mode. A `{field_name}` merge tag in its text, or `{field_name:key}` for one input of a composite field, shows what the visitor entered, as they type: a choice shows its label, a list of choices or a composite field its filled parts, a list field its rows, and a field hidden by conditional logic nothing. Values are escaped, and tags inside attributes (such as an `href`) and tags naming a file, CAPTCHA, administrative or unknown field are left as typed.

```php
FormField::create( ['form_id' => $form->id, 'type' => 'section', 'name' => 'guests', 'label' => 'Guest details',
    'field_config'      => ['description' => 'Tell us who is coming.', 'collapsible' => true],
    'conditional_logic' => ['action' => 'show', 'logic' => 'all', 'rules' => [['field' => 'attending', 'operator' => 'equals', 'value' => 'yes']]],
] );

FormField::create( ['form_id' => $form->id, 'type' => 'heading', 'name' => 'intro', 'label' => 'Personal Information',
    'field_config' => ['level' => 'h3', 'align' => 'center'],
] );

FormField::create( ['form_id' => $form->id, 'type' => 'paragraph', 'name' => 'terms',
    'field_config' => ['content' => 'Please read our <a href="/terms">terms</a> <strong>before</strong> you submit.'],
] );

FormField::create( ['form_id' => $form->id, 'type' => 'html', 'name' => 'summary',
    'field_config' => ['content' => '<p>Thanks, {first_name}. You chose the {plan} plan.</p>'],
] );

FormField::create( ['form_id' => $form->id, 'type' => 'divider', 'name' => 'break'] );
```

## Creating Forms Programmatically

```php
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;

$form = Form::create([
    'name' => 'Contact Form',
    'slug' => 'contact',
    'is_active' => true,
    'success_message' => 'Thank you for contacting us!',
    'settings' => [
        'label_position' => 'above',
        'submit_button_text' => 'Send Message',
    ],
]);

// Add fields
$fields = [
    ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'required' => true],
    ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
    ['type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => true],
];

foreach ($fields as $index => $field) {
    FormField::create([
        'form_id' => $form->id,
        'order' => $index + 1,
        ...$field,
    ]);
}
```

## Duplicating Forms

To duplicate a form with all its fields:

1. Go to the form list
2. Click the duplicate icon on the form row
3. Edit the duplicated form as needed

Or programmatically:

```php
use ArtisanPackUI\Forms\Services\FormService;

$formService = app(FormService::class);
$newForm = $formService->duplicate($originalForm);
```

## Next Steps

- [Form Renderer](Form-Renderer) - Display forms on your site
- [Multi-Step Forms](Multi-Step-Forms) - Create multi-step forms
- [Conditional Logic](Conditional-Logic) - Add conditional field visibility
