Feature Name: Submissions Management Interface
Requested By: Planning Document
Owned By:

/label ~"Type::Feature" ~"Status::Backlog" ~"Phase::10-Submissions"

## What is the Feature

Implement the admin interface for viewing, searching, filtering, and exporting form submissions, including individual submission details, bulk operations, and CSV export.

## Tasks

### SubmissionsList Livewire Component

- [ ] Create `SubmissionsList` component
- [ ] Properties with URL binding:
  - `search` - Text search
  - `status` - Filter (all, unread, read, spam, starred)
  - `dateRange` - Filter (all, today, week, month, year)
  - `sortBy` / `sortDir` - Sorting
  - `selected` - Selected IDs for bulk actions
  - `form` - Optional form filter

- [ ] Computed properties:
  - `submissions` - Paginated query with filters
  - `statusCounts` - Count for each status
  - `forms` - All forms for filter dropdown

### List View UI

- [ ] Header with title and counts
- [ ] Bulk actions dropdown (when items selected)
- [ ] Export CSV button
- [ ] Filter bar:
  - Search input
  - Form dropdown (when viewing all)
  - Date range dropdown
- [ ] Status tabs with counts
- [ ] Submissions table:
  - Checkbox column
  - Submission number (linked)
  - Form name (when viewing all)
  - Summary (first 2 field values)
  - Date (relative)
  - Actions (star, read/unread, delete)
- [ ] Pagination

### Individual Actions

- [ ] `markAsRead(id)` - Mark submission as read
- [ ] `markAsUnread(id)` - Mark submission as unread
- [ ] `toggleStar(id)` - Toggle starred status
- [ ] `toggleSpam(id)` - Toggle spam status
- [ ] `delete(id)` - Delete submission (with confirmation)

### Bulk Actions

- [ ] Select all checkbox
- [ ] `bulkMarkAsRead()` - Mark selected as read
- [ ] `bulkMarkAsUnread()` - Mark selected as unread
- [ ] `bulkMarkAsSpam()` - Mark selected as spam
- [ ] `bulkDelete()` - Delete selected (with confirmation)

### SubmissionDetail Component

- [ ] Create `SubmissionDetail` component or view
- [ ] Header with navigation and actions
- [ ] Submission data card:
  - Field label / value pairs
  - Handle array values (lists)
  - Handle file uploads (download links)
  - Handle empty values gracefully
- [ ] Metadata card:
  - Submitted date/time
  - Page URL
  - IP address
  - User agent
- [ ] Admin notes textarea (auto-save)
- [ ] Action buttons: Star, Spam, Delete

### ExportService

- [ ] Create `ExportService` class
- [ ] `toCsv(Collection $submissions)` method
- [ ] Build headers from form fields
- [ ] Apply `forms.export_headers` filter
- [ ] Build data rows from submission values
- [ ] Apply `forms.export_data` filter
- [ ] Stream response for large exports
- [ ] Include metadata columns (submission #, date, page URL, IP, status)

### Export Feature

- [ ] Export button in submissions list
- [ ] Export selected or all visible
- [ ] Filename: `{form-slug}_submissions_{timestamp}.csv`
- [ ] Proper CSV formatting (escape special characters)

### Search & Filter

- [ ] Full-text search across submission values
- [ ] Search by submission number
- [ ] Filter by form
- [ ] Filter by status
- [ ] Filter by date range
- [ ] Sort by date or submission number

### Visual Indicators

- [ ] Unread submissions highlighted/bold
- [ ] Starred indicator (star icon)
- [ ] Spam badge/indicator
- [ ] Read/unread envelope icons

## Accessibility Notes

- Table must be keyboard navigable
- Checkboxes properly labeled
- Actions have clear labels
- Status changes announced
- Sort direction indicated

## UX Notes

- Unread rows visually distinct
- Quick actions on hover
- Bulk selection is intuitive
- Export provides feedback
- Empty state is helpful

## Testing Notes

- Test list filtering and search
- Test pagination
- Test individual actions (star, read, delete)
- Test bulk actions
- Test CSV export formatting
- Test submission detail view
- Test file download links
- Test admin notes saving

## Documentation Notes

- Document submissions management features
- Document export format
- Document bulk actions

## Related Documents

- [10-submissions-management.md](../10-submissions-management.md)
- [02-models-and-relationships.md](../02-models-and-relationships.md)
- [09-integrations.md](../09-integrations.md)
