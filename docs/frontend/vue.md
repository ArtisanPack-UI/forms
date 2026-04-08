---
title: Vue Components
---

# Vue Components

Vue 3 components for rendering and managing ArtisanPack UI Forms using the Composition API.

## FormRenderer

The main component for displaying forms to users. Fetches the form definition from the REST API and handles the full submission lifecycle.

### Props

| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| `baseUrl` | `string` | Yes | - | API base URL (e.g., `/api/v1/forms`) |
| `formSlug` | `string` | Yes | - | Form slug or ID |
| `clientValidation` | `boolean` | No | `true` | Enable client-side validation |
| `formClass` | `string` | No | - | CSS class for form wrapper |

### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `success` | `SubmitFormResponse` | Emitted after successful submission |
| `error` | `Error` | Emitted on submission error |

### Slots

| Slot | Props | Description |
|------|-------|-------------|
| `#loading` | - | Custom loading skeleton |
| `#error` | `{ message: string }` | Custom error display |
| `#success` | `{ message: string }` | Custom success message |

### Usage

```vue
<script setup lang="ts">
import { FormRenderer } from './vendor/artisanpack-forms/vue';
</script>

<template>
    <!-- Basic usage -->
    <FormRenderer
        base-url="/api/v1/forms"
        form-slug="contact-us"
    />

    <!-- With events and custom class -->
    <FormRenderer
        base-url="/api/v1/forms"
        form-slug="contact-us"
        form-class="max-w-lg mx-auto"
        @success="handleSuccess"
        @error="handleError"
    />

    <!-- With custom slots -->
    <FormRenderer
        base-url="/api/v1/forms"
        form-slug="contact-us"
    >
        <template #loading>
            <MySpinner />
        </template>

        <template #error="{ message }">
            <MyErrorBanner :message="message" />
        </template>

        <template #success="{ message }">
            <MySuccessPage :message="message" />
        </template>
    </FormRenderer>
</template>
```

## useForm Composable

Manages form state, validation, step navigation, file uploads, and submission. Returns reactive refs.

### Return Value

```typescript
const {
    form,               // Ref<FormRenderData | null>
    values,             // Ref<Record<string, unknown>>
    errors,             // Ref<Record<string, string[]>>
    hiddenFields,       // Ref<Record<string, boolean>>
    isLoading,          // Ref<boolean>
    isSubmitting,       // Ref<boolean>
    isSubmitted,        // Ref<boolean>
    currentStep,        // Ref<number> (0-based index)
    totalSteps,         // Ref<number>
    progressPercentage, // Ref<number> (0-100)
    currentFields,      // Ref<FormField[]>
    currentStepData,    // Ref<FormStep | null>
    loadError,          // Ref<string | null>
    setValue,           // (field: string, value: unknown) => void
    setFile,            // (field: string, file: File | File[]) => void
    nextStep,           // () => Promise<void>
    prevStep,           // () => void
    goToStep,           // (step: number) => Promise<void>
    submit,             // () => Promise<void>
    reset,              // () => void
} = useForm({ baseUrl, formSlug, clientValidation, onSuccess, onError });
```

### Usage

```vue
<script setup lang="ts">
import { useForm } from './vendor/artisanpack-forms/vue';

const {
    form, values, errors, isLoading, isSubmitting,
    isSubmitted, setValue, submit
} = useForm({
    baseUrl: '/api/v1/forms',
    formSlug: 'contact',
    onSuccess: (res) => console.log('Done!', res),
});
</script>

<template>
    <div v-if="isLoading">Loading...</div>
    <div v-else-if="isSubmitted">{{ form?.success_message }}</div>
    <form v-else @submit.prevent="submit">
        <div v-for="field in form?.fields" :key="field.id">
            <label>{{ field.label }}</label>
            <input
                :value="values[field.name]"
                @input="setValue(field.name, ($event.target as HTMLInputElement).value)"
            />
            <span
                v-for="(err, i) in errors[field.name]"
                :key="i"
                class="text-red-500"
            >
                {{ err }}
            </span>
        </div>
        <button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Submitting...' : form?.submit_button_text }}
        </button>
    </form>
</template>
```

## useApi Composable

Type-safe API client for communicating with the Forms REST API.

```typescript
import { useApi, ApiError, ApiValidationError } from './vendor/artisanpack-forms/vue';

const api = useApi({ baseUrl: '/api/v1/forms' });

try {
    const form = await api.get('/contact/render');
    const result = await api.post('/contact/submit', formData);
} catch (error) {
    if (error instanceof ApiValidationError) {
        console.log(error.errors); // { field: ['message'] }
    } else if (error instanceof ApiError) {
        console.log(error.status, error.message);
    }
}
```

## useAutoSave Composable

Automatically saves form drafts to prevent data loss.

```typescript
import { useAutoSave } from './vendor/artisanpack-forms/vue';

useAutoSave({
    key: 'contact-form-draft',
    data: formValues,
    interval: 5000, // Save every 5 seconds
});
```

## Field Components

Individual field type components for building custom form layouts:

| Component | Field Types |
|-----------|-------------|
| `TextField` | text, email, phone, number, url, textarea, hidden, time |
| `ChoiceField` | select, select_multiple, radio, checkbox, checkbox_group |
| `AdvancedField` | file, date |
| `LayoutField` | heading, paragraph, divider, html |
| `FieldRenderer` | Renders any field by type (delegates to the above) |

## HoneypotField

Dedicated component for rendering the hidden honeypot spam protection field:

```vue
<HoneypotField :field-name="form.config.honeypot.field_name" />
```

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

```vue
<script setup lang="ts">
import {
    FormsList,
    FormBuilder,
    SubmissionsList,
    SubmissionDetail,
} from './vendor/artisanpack-forms/vue';
</script>

<template>
    <!-- Forms management page -->
    <FormsList base-url="/api/v1/forms" />

    <!-- Form builder -->
    <FormBuilder base-url="/api/v1/forms" :form-id="1" />

    <!-- Submissions list -->
    <SubmissionsList base-url="/api/v1/forms" :form-id="1" />

    <!-- Submission detail -->
    <SubmissionDetail base-url="/api/v1/forms" :form-id="1" :submission-id="42" />
</template>
```

## Next Steps

- [React Components](React) - React implementation
- [TypeScript Types](Typescript-Types) - Type definitions
- [REST API](Api-Rest-Api) - API endpoint reference
