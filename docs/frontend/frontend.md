---
title: Frontend Overview
---

# Frontend Components

ArtisanPack UI Forms provides React and Vue components for rendering and managing forms in JavaScript-based frontends. These components communicate with the [REST API](Api-Rest-Api) and share common validation and conditional logic modules.

## Architecture

```
resources/js/
├── react/              # React components and hooks
│   ├── components/
│   │   ├── FormRenderer.tsx      # Main form renderer
│   │   ├── MultiStepForm.tsx     # Multi-step wrapper
│   │   ├── fields/               # Field type components
│   │   └── admin/                # Admin management components
│   ├── hooks/
│   │   ├── useForm.ts            # Form state management
│   │   ├── useApi.ts             # API client
│   │   └── useAutoSave.ts        # Draft auto-saving
│   └── index.ts                  # Public exports
├── vue/                # Vue components and composables
│   ├── components/
│   │   ├── FormRenderer.vue      # Main form renderer
│   │   ├── MultiStepForm.vue     # Multi-step wrapper
│   │   ├── HoneypotField.vue     # Honeypot spam protection
│   │   ├── fields/               # Field type components
│   │   └── admin/                # Admin management components
│   ├── composables/
│   │   ├── useForm.ts            # Form state management
│   │   ├── useApi.ts             # API client
│   │   └── useAutoSave.ts        # Draft auto-saving
│   └── index.ts                  # Public exports
├── shared/             # Framework-agnostic utilities
│   ├── validation.ts             # Client-side validation
│   └── conditionalLogic.ts       # Conditional visibility
└── types/
    └── artisanpack-forms.d.ts    # TypeScript definitions
```

## Installation

Use the `forms:install-frontend` Artisan command to publish components to your project:

```bash
# Interactive - prompts for stack choice
php artisan forms:install-frontend

# React
php artisan forms:install-frontend --stack=react

# Vue
php artisan forms:install-frontend --stack=vue

# Overwrite existing files
php artisan forms:install-frontend --stack=react --force
```

This publishes files to `resources/js/vendor/artisanpack-forms/`.

## Features

Both React and Vue implementations include:

- **Form Rendering**: Fetch form definitions from the API and render all field types
- **Multi-Step Forms**: Progress bar, step navigation, and per-step validation
- **Conditional Logic**: Show/hide fields based on user input (19 operators, AND/OR logic)
- **Client-Side Validation**: Validate all field types before submission
- **Honeypot Protection**: Built-in spam protection
- **File Uploads**: Handle single and multiple file uploads
- **Admin Components**: Full form management interface (builder, submissions, notifications)

## Topics

- [React Components](Frontend-React) - React implementation guide
- [Vue Components](Frontend-Vue) - Vue implementation guide
- [TypeScript Types](Frontend-Typescript-Types) - Type definitions reference
- [Artisan Commands](Advanced-Artisan-Commands) - CLI commands including `forms:install-frontend`

## Quick Start

### React

```tsx
import { FormRenderer } from './vendor/artisanpack-forms/react';

function ContactPage() {
    return (
        <FormRenderer
            baseUrl="/api/v1/forms"
            formSlug="contact-us"
            onSuccess={(response) => console.log('Submitted!', response)}
            onError={(error) => console.error('Error:', error)}
        />
    );
}
```

### Vue

```vue
<script setup lang="ts">
import { FormRenderer } from './vendor/artisanpack-forms/vue';
</script>

<template>
    <FormRenderer
        base-url="/api/v1/forms"
        form-slug="contact-us"
        @success="(response) => console.log('Submitted!', response)"
        @error="(error) => console.error('Error:', error)"
    />
</template>
```
