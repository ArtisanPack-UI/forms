---
title: Policies
---

# Policies

Authorization policies for ArtisanPack UI Forms.

## Overview

The package includes two authorization policies:

- **FormPolicy** - Controls access to forms
- **SubmissionPolicy** - Controls access to submissions

## FormPolicy

Controls who can view, create, update, and delete forms.

### Policy Methods

| Method | Description |
|--------|-------------|
| `viewAny` | Can list all forms |
| `view` | Can view a specific form |
| `create` | Can create new forms |
| `update` | Can update a form |
| `delete` | Can delete a form |
| `restore` | Can restore soft-deleted forms |
| `forceDelete` | Can permanently delete forms |

### Default Behavior

By default, the package uses permissive authorization:

```php
// config/artisanpack/forms.php
'authorization' => [
    'restrict_by_owner' => false, // Anyone can access any form
    'allow_admin_bypass' => true,
    'user_model' => 'App\\Models\\User',
],
```

**Security Warning**: With `restrict_by_owner = false`, any authenticated user can access all forms. Enable ownership enforcement for multi-user applications.

### Ownership Enforcement

Enable ownership-based access:

```php
// config/artisanpack/forms.php
'authorization' => [
    'restrict_by_owner' => true,
],
```

With this enabled:

```php
// Users can only access their own forms
$user->can('view', $form); // true if $form->user_id === $user->id

// Admins bypass ownership checks
$admin->can('view', $form); // true if $admin->is_admin
```

### Admin Bypass

Users with `is_admin` attribute can bypass ownership:

```php
// config/artisanpack/forms.php
'authorization' => [
    'allow_admin_bypass' => true,
],
```

Ensure your User model has the `is_admin` attribute:

```php
// User model
protected $casts = [
    'is_admin' => 'boolean',
];
```

### Custom Policy

Override the default policy:

```php
// app/Policies/CustomFormPolicy.php

namespace App\Policies;

use App\Models\User;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Policies\FormPolicy;

class CustomFormPolicy extends FormPolicy
{
    public function update(User $user, Form $form): bool
    {
        // Add custom logic
        if ($user->hasRole('editor')) {
            return true;
        }

        return parent::update($user, $form);
    }

    public function delete(User $user, Form $form): bool
    {
        // Only allow deletion for admins
        return $user->is_admin;
    }
}
```

Register the custom policy:

```php
// app/Providers/AuthServiceProvider.php

use ArtisanPackUI\Forms\Models\Form;
use App\Policies\CustomFormPolicy;

protected $policies = [
    Form::class => CustomFormPolicy::class,
];
```

---

## SubmissionPolicy

Controls access to form submissions.

### Policy Methods

| Method | Description |
|--------|-------------|
| `viewAny` | Can list submissions |
| `view` | Can view a specific submission |
| `delete` | Can delete a submission |
| `export` | Can export submissions |
| `downloadUpload` | Can download uploaded files |

### Default Behavior

Submission access follows form access:

```php
// If user can view the form, they can view its submissions
$user->can('view', $submission); // Checks form access
```

### Custom Policy

```php
// app/Policies/CustomSubmissionPolicy.php

namespace App\Policies;

use App\Models\User;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Policies\SubmissionPolicy;

class CustomSubmissionPolicy extends SubmissionPolicy
{
    public function view(User $user, FormSubmission $submission): bool
    {
        // Custom access rules
        if ($submission->form->is_public) {
            return true;
        }

        return parent::view($user, $submission);
    }

    public function export(User $user, FormSubmission $submission): bool
    {
        // Only managers can export
        return $user->hasRole('manager') || $user->is_admin;
    }
}
```

---

## Using Policies

### In Controllers

```php
public function show(Form $form)
{
    $this->authorize('view', $form);

    return view('forms.show', compact('form'));
}

public function destroy(FormSubmission $submission)
{
    $this->authorize('delete', $submission);

    $submission->delete();

    return redirect()->back()->with('success', 'Submission deleted.');
}
```

### In Blade Templates

```blade
@can('update', $form)
    <a href="{{ route('forms.edit', $form) }}">Edit</a>
@endcan

@can('delete', $submission)
    <button wire:click="delete({{ $submission->id }})">Delete</button>
@endcan
```

### In Livewire Components

```php
public function deleteSubmission(int $id): void
{
    $submission = FormSubmission::findOrFail($id);

    $this->authorize('delete', $submission);

    $submission->delete();
}
```

### Using Gates

```php
use Illuminate\Support\Facades\Gate;

if (Gate::allows('update', $form)) {
    // User can update the form
}

if (Gate::denies('delete', $submission)) {
    abort(403, 'You cannot delete this submission.');
}
```

---

## Configuration Reference

```php
// config/artisanpack/forms.php
'authorization' => [
    // Enable ownership-based access control
    // When true: users can only access their own forms
    // When false: any authenticated user can access any form
    'restrict_by_owner' => env('FORMS_RESTRICT_BY_OWNER', false),

    // Allow users with is_admin=true to bypass ownership checks
    'allow_admin_bypass' => env('FORMS_ALLOW_ADMIN_BYPASS', true),

    // User model class for ownership relationships
    'user_model' => env('FORMS_USER_MODEL', 'App\\Models\\User'),
],
```

## Testing Policies

```php
use ArtisanPackUI\Forms\Models\Form;
use App\Models\User;

test('owner can update their form', function () {
    $user = User::factory()->create();
    $form = Form::factory()->create(['user_id' => $user->id]);

    config(['artisanpack.forms.authorization.restrict_by_owner' => true]);

    $this->assertTrue($user->can('update', $form));
});

test('non-owner cannot update others form', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $form = Form::factory()->create(['user_id' => $owner->id]);

    config(['artisanpack.forms.authorization.restrict_by_owner' => true]);

    $this->assertFalse($other->can('update', $form));
});

test('admin can update any form', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $form = Form::factory()->create();

    config(['artisanpack.forms.authorization.restrict_by_owner' => true]);
    config(['artisanpack.forms.authorization.allow_admin_bypass' => true]);

    $this->assertTrue($admin->can('update', $form));
});
```

## Next Steps

- [Configuration](../installation/configuration.md) - Authorization settings
- [Models](./models.md) - Model documentation
- [Services](./services.md) - Service documentation
