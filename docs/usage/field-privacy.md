---
title: Field Privacy & Visibility
---

# Field Privacy & Visibility

Since 1.6.0 every input field has a **Visibility** setting and three privacy settings in the builder's **Advanced** tab. Use them for values that the public should not fill in, or that most admins should not see in full: internal notes, ID numbers or bank details.

| Setting | Key (`field_config`) | What it does |
|---------|----------------------|--------------|
| **Visibility** | `visibility` | `visible` (default) or `administrative`. Administrative fields are left off the public form, and admins fill them in on the submission. |
| **Do not store** | `do_not_store` | The value goes to the submission's notifications and webhooks but is never saved. |
| **Mask in admin** | `mask_in_admin` | Stored values show only their last four characters to users who may not reveal masked values. |
| **Redact after (days)** | `redact_after_days` | `forms:prune-submissions` clears stored values older than this many days (1–3650). |

Signature and CAPTCHA fields have none of these settings, and consent fields have no **Visibility** setting. Layout elements collect nothing, so they have none either. File fields have all four. Their files are handled with the file in mind; see [File Uploads](Usage-File-Uploads#visibility-and-privacy).

```php
use ArtisanPackUI\Forms\Models\FormField;

FormField::create( [
    'form_id'      => $form->id,
    'type'         => 'text',
    'name'         => 'tax_id',
    'label'        => 'Tax ID',
    'field_config' => [
        'mask_in_admin'     => true,
        'redact_after_days' => 90,
    ],
] );
```

## Administrative Fields

An administrative field is not on the public form:

- The Livewire, React and Vue renderers leave it out, and so does the render API (`GET /{form}/render`).
- It is not validated on submit, and a value sent for it is dropped.
- Admins fill it in on the submission detail, in the **Administrative Fields** card of all three admin UIs. Values are checked with the field's own rules when they are saved.
- In the API, `GET /{form}/submissions/{submission}` returns the fields as `administrative_fields`, and the update endpoint takes their values in `admin_values`. See [REST API](Api-Rest-Api#update-submission).

Use administrative fields for internal notes, a follow-up status or an assigned owner. Fields masked from the current user are not offered for editing.

## Do Not Store

A "Do not store" field is validated and its value is passed to the notifications and webhooks for that submission, then dropped. The value travels to queued notification and webhook jobs in an encrypted job payload, and no `form_submission_values` row is written. The admin, the API and exports never see it.

For file fields, the files are kept only until the notification and webhook jobs have run, then deleted. Admin notifications that include the submission data attach them; **autoresponders never attach them**, because an autoresponder goes to an address the visitor typed. See [File Uploads](Usage-File-Uploads#do-not-store).

## Mask in Admin

For users who may not reveal masked values:

- The submission detail and list show the value masked: `••••` followed by its last four characters, such as `••••1234`. Values shorter than eight characters are masked in full (`••••`).
- Submission searches skip the field.
- CSV and JSON exports leave the field's column out.
- In the API, `value` and `display_value` hold the masked string, and `value_array`, `display_values`, `display_html`, `upload_id` and `upload_ids` are empty. `is_masked` is `true`. See [API Resources](Api-Resources#formsubmissionvalueresource).
- A masked file field's file name, preview and download link are hidden, and its download routes return 403.

Users who may reveal masked values see everything in full.

### Who May Reveal Masked Values

The `revealMaskedValues` ability on `SubmissionPolicy` decides. By default only admins may reveal, meaning users that `SubmissionPolicy::isAdmin()` recognises (`is_admin`). The decision then runs through the `ap.forms.canRevealMaskedValues` filter, so you can grant it by role or permission instead:

```php
use function addFilter;

addFilter( 'ap.forms.canRevealMaskedValues', function ( bool $allowed, $user ): bool {
    return $allowed || ( null !== $user && $user->hasRole( 'privacy-officer' ) );
} );
```

The filter must return `true` exactly; any other value denies.

### Who May Change Privacy Settings

Anyone who can edit a form could otherwise switch a mask off and read every stored value. So these changes to a masked field need the `changeFieldPrivacy` ability on the form, as well as `update`:

- turning **Mask in admin** off,
- changing the field's type to one without privacy settings (such as Signature),
- deleting the field while it has stored values.

Renaming a masked field needs no extra ability: each stored value keeps its own mask flag, so it stays masked under any name.

`changeFieldPrivacy` defaults to the `revealMaskedValues` ability, so by default only users who may already see the values can unmask them. The Livewire builder and the field API both check it, and answer 403 without it. Override it in a custom `FormPolicy` (see [Policies](Api-Policies)).

A Calculation field's formula can't reference a masked field, since its result would show the masked value in full.

### What Masking Does Not Cover

Masking only hides values in the admin from users who may not reveal them. It is not a way to keep data from people who can edit the form:

- **Notifications** are configured by form editors. A `{field}` merge tag or the full submission table in a notification includes masked values in full, wherever the notification is sent.
- **Webhooks** receive the full submission, masked values included.
- **Do not store** values reach notifications and webhooks too; that is what the setting is for.

Restrict who may edit forms, notifications and webhooks (the `update` and `manageNotifications` form abilities) if those values must stay private.

## Redact After N Days

`forms:prune-submissions` first redacts values older than their field's **Redact after (days)** period, measured from the submission date. This runs whether or not a retention period is set. A redacted value is emptied and gets a `redacted_at` timestamp (run the migrations). It shows as "[Redacted]" in the admin, and the API returns `is_redacted: true`. For file fields, the stored files and their upload records are deleted.

Schedule the command so redaction happens on time:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command( 'forms:prune-submissions' )->daily();
```

## Helper Methods

| Method | Description |
|--------|-------------|
| `FormField::isAdministrative()` | Whether the field's visibility is administrative. |
| `FormField::storesValues()` | False when **Do not store** is on. |
| `FormField::isMaskedInAdmin()` | Whether **Mask in admin** is on. |
| `FormField::getRedactAfterDays()` | The redaction period, or null. |
| `FormSubmissionValue::isMasked()` | Whether the value belongs to a masked field. |
| `FormSubmissionValue::isRedacted()` | Whether the value has been redacted. |
| `FieldPrivacy::mask( $value )` | Masks a string the way the admin does. |
| `FieldPrivacy::canReveal( $user )` | Whether a user may reveal masked values. |

## Next Steps

- [File Uploads](Usage-File-Uploads#visibility-and-privacy) - Privacy settings for file fields
- [Form Builder](Usage-Form-Builder) - Every field setting
- [Policies](Api-Policies) - Authorization abilities
