Feature Name: Form Management (CRUD)
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::2-FormCRUD"

## What is the Feature

Implement basic form management functionality including listing, creating, editing, duplicating, and deleting forms. This establishes the foundation for form administration before the full drag-and-drop builder is implemented.

## Tasks

### FormService

- [ ] Create `FormService` class for business logic
  - `create(array $data): Form` - Create new form
  - `update(Form $form, array $data): Form` - Update form
  - `delete(Form $form): bool` - Delete form (with related data)
  - `duplicate(Form $form): Form` - Clone form with fields and notifications
  - `publish(Form $form): void` - Set form as active
  - `unpublish(Form $form): void` - Set form as inactive
  - `getBySlug(string $slug): ?Form` - Find form by slug

### FormController

- [ ] Create `FormController` for web routes
  - `index()` - List all forms
  - `create()` - Show create form page
  - `store(Request $request)` - Save new form
  - `edit(Form $form)` - Show edit form page
  - `update(Request $request, Form $form)` - Update form
  - `destroy(Form $form)` - Delete form

- [ ] Create form request classes
  - `StoreFormRequest` - Validation for creating forms
  - `UpdateFormRequest` - Validation for updating forms

### Admin Routes

- [ ] Register admin routes in `routes/web.php`
  ```php
  Route::prefix('admin/forms')->group(function () {
      Route::get('/', [FormController::class, 'index'])->name('forms.index');
      Route::get('/create', [FormController::class, 'create'])->name('forms.create');
      Route::post('/', [FormController::class, 'store'])->name('forms.store');
      Route::get('/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
      Route::put('/{form}', [FormController::class, 'update'])->name('forms.update');
      Route::delete('/{form}', [FormController::class, 'destroy'])->name('forms.destroy');
  });
  ```

### FormsList Livewire Component

- [ ] Create `FormsList` Livewire component
  - Properties: search, sortBy, sortDir, perPage
  - Computed: forms (paginated query)
  - Display columns: name, slug, fields count, submissions count, status, created date
  - Actions: edit, duplicate, delete, toggle publish

- [ ] Implement search functionality
  - Search by name and slug
  - Debounced input

- [ ] Implement sorting
  - Sort by name, created date, submissions count
  - Toggle sort direction

- [ ] Implement filtering
  - Filter by status (all, active, inactive)

### FormsList View

- [ ] Header with title and "Create Form" button
- [ ] Search input
- [ ] Filter tabs (All, Active, Inactive)
- [ ] Forms table with columns:
  - Name (linked to edit/builder)
  - Slug
  - Fields count
  - Submissions count (with unread badge)
  - Status (active/inactive badge)
  - Created date
  - Actions dropdown (Edit, Duplicate, Delete, View Submissions)
- [ ] Empty state when no forms
- [ ] Pagination

### CreateForm Modal/Page

- [ ] Create form modal or dedicated page
- [ ] Form fields:
  - Name (required)
  - Slug (auto-generated from name, editable)
  - Description (optional)
- [ ] Validation with error display
- [ ] Redirect to form builder after creation

### Form Settings

- [ ] Basic settings section (can be in builder or separate)
  - Form name
  - Form slug
  - Description
  - Submit button text
  - Success message
  - Redirect URL (optional)
  - Active/Inactive toggle

### Form Actions

- [ ] Duplicate form
  - Copy form with all fields, steps, and notifications
  - Append "(Copy)" to name
  - Generate new slug
  - Set as inactive by default
  - Show success notification

- [ ] Delete form
  - Confirmation modal
  - Delete related fields, steps, submissions, notifications
  - Show success notification

- [ ] Publish/Unpublish toggle
  - Quick toggle in list view
  - Update is_active flag

### Standalone vs CMS Mode

- [ ] Detect if `artisanpack-ui/cms-framework` is installed
- [ ] Standalone mode:
  - Use package's own layout
  - Register navigation items
- [ ] CMS mode:
  - Integrate with CMS layout
  - Add to CMS navigation
  - Use CMS permission system

## Accessibility Notes

- Table must be keyboard navigable
- Action buttons have clear labels
- Modal can be closed with Escape
- Focus management when modal opens/closes
- Status changes announced to screen readers

## UX Notes

- Auto-generate slug from name as user types
- Confirm before destructive actions (delete)
- Show loading states during operations
- Toast notifications for success/error
- Quick actions accessible without opening form

## Testing Notes

- Test form creation with valid/invalid data
- Test slug auto-generation and uniqueness
- Test form duplication copies all related data
- Test form deletion removes related data
- Test publish/unpublish toggle
- Test search and filtering
- Test sorting
- Test pagination
- Test standalone vs CMS mode detection

## Documentation Notes

- Document admin routes
- Document FormService methods
- Document standalone vs CMS mode behavior
- Include screenshots of the forms list

## Related Documents

- [12-implementation-order.md](../12-implementation-order.md) - Phase 2 specification
- [02-models-and-relationships.md](../02-models-and-relationships.md) - Form model
- [03-form-builder.md](../03-form-builder.md) - Builds on this foundation
