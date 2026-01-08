---
title: FormsList Component
---

# FormsList Component

The FormsList component displays a paginated list of forms with search, filtering, and actions.

## Basic Usage

```blade
<livewire:forms::forms-list />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `perPage` | int | 15 | Items per page |
| `sortBy` | string | 'created_at' | Sort column |
| `sortDirection` | string | 'desc' | Sort direction |

## Features

- Search by name, slug, description
- Filter by status (active/inactive)
- Sort by columns
- Pagination
- Quick actions (edit, delete, duplicate, view submissions)

## Component Structure

```blade
<div class="forms-list">
    {{-- Search & Filters --}}
    <div class="filters">
        <input wire:model.live.debounce.300ms="search" placeholder="Search forms...">

        <select wire:model.live="statusFilter">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </div>

    {{-- Forms Table --}}
    <table>
        <thead>
            <tr>
                <th wire:click="sortBy('name')">Name</th>
                <th wire:click="sortBy('slug')">Slug</th>
                <th wire:click="sortBy('submissions_count')">Submissions</th>
                <th wire:click="sortBy('is_active')">Status</th>
                <th wire:click="sortBy('created_at')">Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($forms as $form)
                <tr>
                    <td>{{ $form->name }}</td>
                    <td>{{ $form->slug }}</td>
                    <td>{{ $form->submissions_count }}</td>
                    <td>{{ $form->is_active ? 'Active' : 'Inactive' }}</td>
                    <td>{{ $form->created_at->format('M d, Y') }}</td>
                    <td>
                        <a href="{{ route('forms.edit', $form) }}">Edit</a>
                        <a href="{{ route('forms.submissions.index', $form) }}">Submissions</a>
                        <button wire:click="duplicate({{ $form->id }})">Duplicate</button>
                        <button wire:click="delete({{ $form->id }})">Delete</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Pagination --}}
    {{ $forms->links() }}
</div>
```

## Methods

### sortBy

Sort the list by column:

```php
public function sortBy(string $column): void
{
    if ($this->sortBy === $column) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }
}
```

### duplicate

Duplicate a form:

```php
public function duplicate(int $id): void
{
    $form = Form::findOrFail($id);
    $this->authorize('create', Form::class);

    $newForm = $this->formService->duplicate($form);

    session()->flash('success', "Form duplicated: {$newForm->name}");
}
```

### delete

Delete a form:

```php
public function delete(int $id): void
{
    $form = Form::findOrFail($id);
    $this->authorize('delete', $form);

    $this->formService->delete($form);

    session()->flash('success', 'Form deleted.');
}
```

### toggleStatus

Toggle form active status:

```php
public function toggleStatus(int $id): void
{
    $form = Form::findOrFail($id);
    $this->authorize('update', $form);

    $form->update(['is_active' => !$form->is_active]);
}
```

## Events Emitted

| Event | Payload | Description |
|-------|---------|-------------|
| `form-deleted` | `{ formId }` | Form was deleted |
| `form-duplicated` | `{ formId, newFormId }` | Form was duplicated |
| `form-status-changed` | `{ formId, status }` | Status toggled |

## Customizing the View

Publish and edit:

```bash
php artisan vendor:publish --tag=forms-views
```

Edit `resources/views/vendor/forms/livewire/forms-list.blade.php`.

## Extending the Component

```php
namespace App\Livewire;

use ArtisanPackUI\Forms\Livewire\FormsList;

class CustomFormsList extends FormsList
{
    // Add custom filters
    public string $departmentFilter = '';

    public function getFormsProperty()
    {
        $query = parent::getFormsProperty();

        if ($this->departmentFilter) {
            $query->where('department', $this->departmentFilter);
        }

        return $query;
    }
}
```

## Next Steps

- [FormBuilder](./form-builder.md) - Form builder component
- [SubmissionsList](./submissions-list.md) - Submissions listing
