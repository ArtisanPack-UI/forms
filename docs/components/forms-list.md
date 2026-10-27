---
title: FormsList Component
---

# FormsList Component

The FormsList component displays a paginated list of forms with search, filtering, and actions.

## Basic Usage

```blade
<livewire:forms-list />
```

## Properties

The component has no `mount()` parameters. These public properties can be passed as starting values, and are kept in the query string:

| Property | Type | Default | Query string | Description |
|----------|------|---------|--------------|-------------|
| `search` | string | `''` | `q` | Search by name or slug |
| `statusFilter` | string | `'all'` | `status` | `all`, `active` or `inactive` |
| `sortBy` | string | `'created_at'` | `sort` | `created_at`, `name` or `submissions` |
| `sortDirection` | string | `'desc'` | `dir` | `asc` or `desc` |

Items per page come from the `artisanpack.forms.admin.per_page` config option (default 15).

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

The component does not dispatch Livewire events. Its actions (`duplicate`, `delete`, `togglePublish`) flash a success message to the session instead. For server-side hooks, listen for the `FormCreated`, `FormUpdated` and `FormDeleted` Laravel events.

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

- [FormBuilder](Form-Builder) - Form builder component
- [SubmissionsList](Submissions-List) - Submissions listing
