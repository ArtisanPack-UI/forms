---
title: React Components
---

# React Components

React components for rendering and managing ArtisanPack UI Forms.

## FormRenderer

The main component for displaying forms to users. Fetches the form definition from the REST API and handles the full submission lifecycle.

### Props

| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `baseUrl` | `string` | Yes | - | API base URL (e.g., `/api/v1/forms`) |
| `formSlug` | `string` | Yes | - | Form slug or ID |
| `onSuccess` | `(response) => void` | No | - | Callback after successful submission |
| `onError` | `(error) => void` | No | - | Callback on submission error |
| `clientValidation` | `boolean` | No | `true` | Enable client-side validation |
| `className` | `string` | No | - | CSS class for wrapper element |
| `loadingComponent` | `ReactNode` | No | Skeleton | Custom loading UI |
| `errorComponent` | `ComponentType<{message}>` | No | Default | Custom error UI |
| `successComponent` | `ComponentType<{message}>` | No | Default | Custom success UI |
| `fieldComponents` | `FieldComponentMap` | No | - | Host components that overlay the built-ins by field type (see [Custom Field Types](#custom-field-types)) |

### Usage

```tsx
import { FormRenderer } from './vendor/artisanpack-forms/react';

// Basic usage
<FormRenderer
    baseUrl="/api/v1/forms"
    formSlug="contact-us"
/>

// With callbacks and custom styling
<FormRenderer
    baseUrl="/api/v1/forms"
    formSlug="contact-us"
    className="max-w-lg mx-auto"
    onSuccess={(response) => {
        console.log('Submission ID:', response.submission_id);
        if (response.redirect_url) {
            window.location.href = response.redirect_url;
        }
    }}
    onError={(error) => {
        alert('Something went wrong. Please try again.');
    }}
/>

// With custom loading and error components
<FormRenderer
    baseUrl="/api/v1/forms"
    formSlug="contact-us"
    loadingComponent={<MySpinner />}
    errorComponent={({ message }) => <MyErrorBanner message={message} />}
    successComponent={({ message }) => <MySuccessPage message={message} />}
/>
```

## useForm Hook

Manages form state, validation, step navigation, file uploads, and submission.

### Return Value

```typescript
const {
    form,               // FormRenderData | null - loaded form definition
    values,             // Record<string, unknown> - current field values
    errors,             // Record<string, string[]> - validation errors by field
    hiddenFields,       // Record<string, boolean> - conditional visibility
    isLoading,          // boolean - form definition loading
    isSubmitting,       // boolean - submission in progress
    isSubmitted,        // boolean - successfully submitted
    currentStep,        // number - current step index (0-based)
    totalSteps,         // number - total step count
    progressPercentage, // number - 0-100 progress
    currentFields,      // FormField[] - fields for current step
    currentStepData,    // FormStep | null - current step metadata
    loadError,          // string | null - form load error
    setValue,           // (field: string, value: unknown) => void
    setFile,            // (field: string, file: File | File[]) => void
    nextStep,           // () => Promise<void> - validate and advance
    prevStep,           // () => void - go back one step
    goToStep,           // (step: number) => Promise<void> - jump to step
    submit,             // () => Promise<void> - submit the form
    reset,              // () => void - reset to initial state
} = useForm({ baseUrl, formSlug, clientValidation, onSuccess, onError });
```

### Usage

```tsx
import { useForm } from './vendor/artisanpack-forms/react';

function CustomForm() {
    const {
        form, values, errors, isLoading, isSubmitting,
        isSubmitted, setValue, submit
    } = useForm({
        baseUrl: '/api/v1/forms',
        formSlug: 'contact',
        onSuccess: (res) => console.log('Done!', res),
    });

    if (isLoading) return <p>Loading...</p>;
    if (isSubmitted) return <p>{form?.success_message}</p>;

    return (
        <form onSubmit={(e) => { e.preventDefault(); submit(); }}>
            {form?.fields.map((field) => (
                <div key={field.id}>
                    <label>{field.label}</label>
                    <input
                        value={values[field.name] as string ?? ''}
                        onChange={(e) => setValue(field.name, e.target.value)}
                    />
                    {errors[field.name]?.map((err, i) => (
                        <span key={i} className="text-red-500">{err}</span>
                    ))}
                </div>
            ))}
            <button type="submit" disabled={isSubmitting}>
                {isSubmitting ? 'Submitting...' : form?.submit_button_text}
            </button>
        </form>
    );
}
```

## useApi Hook

Type-safe API client for communicating with the Forms REST API.

```typescript
import { useApi, ApiError, ApiValidationError } from './vendor/artisanpack-forms/react';

const api = useApi({ baseUrl: '/api/v1/forms' });

try {
    const form = await api.get(`/contact/render`);
    const result = await api.post(`/contact/submit`, formData);
} catch (error) {
    if (error instanceof ApiValidationError) {
        console.log(error.errors); // { field: ['message'] }
    } else if (error instanceof ApiError) {
        console.log(error.status, error.message);
    }
}
```

## useAutoSave Hook

Automatically saves form drafts to prevent data loss.

```typescript
import { useAutoSave } from './vendor/artisanpack-forms/react';

useAutoSave({
    key: 'contact-form-draft',
    data: formValues,
    interval: 5000, // Save every 5 seconds
});
```

## Field Components

Individual field type components are available for building custom form layouts:

| Component | Field Types |
|-----------|-------------|
| `TextField` | text, email, phone, number, url, textarea, hidden, time |
| `SelectField` | select |
| `SelectMultipleField` | select_multiple |
| `RadioField` | radio |
| `CheckboxField` | checkbox |
| `CheckboxGroupField` | checkbox_group |
| `FileField` | file |
| `DateField` | date |
| `HeadingField` | heading |
| `ParagraphField` | paragraph |
| `DividerField` | divider |
| `HtmlField` | html |

## Custom Field Types

The server side already lets a package register a custom field type, its
validation, and its Blade rendering through the `ap.forms.*` filters (e.g.
`artisanpack-ui/bookings` registers a `booking_slot` type). The React renderer
exposes a matching extensibility seam so a host can supply the renderer and
builder UI for that type — overlaying, not replacing, the built-ins.

Register once at application bootstrap and every `FormRenderer`, `FieldPalette`,
and `FieldEditor` picks it up automatically. Registrations are resync-safe:
they live in host code, not in the vendored package source.

```tsx
import {
    registerFieldComponent,
    registerFieldPaletteGroup,
    registerFieldSettings,
    registerFieldCardPreview,
} from './vendor/artisanpack-forms/react';
import type {
    FieldComponent,
    CustomFieldSettingsProps,
} from './vendor/artisanpack-forms/react';

// 1. Public renderer component for the custom type.
const BookingSlotField: FieldComponent = ( { field, value, onChange } ) => (
    // ...service select -> month calendar -> slot list
    <div>{/* render the booking slot picker */}</div>
);
registerFieldComponent( 'booking_slot', BookingSlotField );

// 2. Builder palette entry (icon is raw SVG path data, 16x16 viewBox).
registerFieldPaletteGroup( {
    label: 'Bookings',
    fields: [
        {
            type: 'booking_slot',
            label: 'Booking Slot',
            icon: 'booking_slot',
            category: 'advanced',
            iconPath: 'M4 1h8a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V2a1 1 0 011-1z',
        },
    ],
} );

// 3. Custom settings panel in the field editor's General tab. `allFields`
//    carries the other fields in the form, so the panel can map the booking's
//    name/email/phone to existing form fields — the React equivalent of the
//    server-side `ap.forms.fieldSettings` filter's `Form` argument.
function BookingSlotSettings( { field, allFields, updateField }: CustomFieldSettingsProps ) {
    return (
        <div>
            {/* e.g. a <select> of allFields for the "Name Form Field" mapping */}
            {/* inputs that call updateField({ field_config: ... }) */}
        </div>
    );
}
registerFieldSettings( 'booking_slot', BookingSlotSettings );

// 4. Live preview on the builder canvas card (the React equivalent of the
//    server-side `ap.forms.fieldCardPreview` filter).
registerFieldCardPreview( 'booking_slot', ( { field } ) => (
    <div>{/* e.g. a mini calendar / "Choose a time" preview */}</div>
) );
```

Every seam also has an equivalent prop for explicit, per-instance use without
the global registry:

| Component | Prop | Overlays |
|-----------|------|----------|
| `FormRenderer` / `FieldRenderer` | `fieldComponents` | Built-in renderer components |
| `FieldPalette` / `FormBuilder` | `extraGroups` / `paletteExtraGroups` | Built-in palette groups |
| `FieldEditor` / `FormBuilder` | `customSettings` / `fieldCustomSettings` | Built-in editor settings |
| `FormBuilder` | `fieldCardPreviews` | Generic builder canvas card |

Resolution order for a given type is: per-instance prop, then module-level
registration, then the built-in. Registering an existing type overrides its
built-in; registering a new type adds it alongside the built-ins.

The `FieldType` union is widened to `BuiltInFieldType | (string & {})`, so a
host can legally construct and hold a custom field (e.g. `booking_slot`) across
`FormField`, `StoreFieldRequest`, `FieldPaletteItem`, and the admin editor
props while keeping autocomplete for the built-in types.

## Admin Components

Full admin management components for building form administration interfaces:

| Component | Description |
|-----------|-------------|
| `FormsList` | List and manage all forms |
| `FormBuilder` | Drag-and-drop form builder |
| `FieldEditor` | Edit individual field properties |
| `FieldPalette` | Field type picker for the builder |
| `ConditionalLogicEditor` | Configure field visibility rules |
| `NotificationEditor` | Manage email notifications |
| `SubmissionsList` | View and manage submissions |
| `SubmissionDetail` | View a single submission |

```tsx
import {
    FormsList,
    FormBuilder,
    SubmissionsList,
    SubmissionDetail,
} from './vendor/artisanpack-forms/react';

// Forms management page
<FormsList baseUrl="/api/v1/forms" />

// Form builder
<FormBuilder baseUrl="/api/v1/forms" formId={1} />

// Submissions list
<SubmissionsList baseUrl="/api/v1/forms" formId={1} />

// Submission detail
<SubmissionDetail baseUrl="/api/v1/forms" formId={1} submissionId={42} />
```

## Next Steps

- [Vue Components](Vue) - Vue implementation
- [TypeScript Types](Typescript-Types) - Type definitions
- [REST API](Api-Rest-Api) - API endpoint reference
