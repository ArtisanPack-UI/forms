---
title: Requirements
---

# Requirements

Before installing ArtisanPack UI Forms, ensure your environment meets these requirements.

## System Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2 or higher |
| Laravel | 11.x or 12.x |
| Livewire | 3.x |
| Database | MySQL 8.0+, PostgreSQL 13+, SQLite 3.35+ |

## PHP Extensions

The following PHP extensions are required:

- `fileinfo` - For file upload MIME type detection
- `mbstring` - For string handling
- `json` - For JSON encoding/decoding
- `openssl` - For encryption (webhooks)

## Required Packages

These packages are automatically installed as dependencies:

| Package | Version | Purpose |
|---------|---------|---------|
| `livewire/livewire` | ^3.0 | Reactive UI components |
| `artisanpack-ui/hooks` | ^1.0 | Filter and action hooks |
| `artisanpack-ui/accessibility` | ^1.0 | WCAG compliance utilities |

## Optional Packages

These packages enhance functionality but are not required:

| Package | Purpose |
|---------|---------|
| `artisanpack-ui/livewire-ui-components` | Pre-built UI components for admin interface |
| `league/csv` | Enhanced CSV export functionality |
| `maatwebsite/excel` | Excel export functionality |

## Browser Support

The admin interface supports:

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Storage Requirements

- **File Uploads**: Adequate disk space for uploaded files (configurable location)
- **Database**: Space for form definitions and submissions

## Queue Requirements (Recommended)

For optimal performance, a queue worker is recommended:

- Redis, Database, or SQS queue driver
- Supervisor or similar process manager for production

## Permissions

Ensure the following directories are writable:

- `storage/app/form-uploads/` (or your configured upload directory)
- `storage/framework/cache/`
- `storage/framework/sessions/`
- `storage/logs/`

## Verifying Requirements

Run the following commands to verify your environment:

```bash
# Check PHP version
php -v

# Check Laravel version
php artisan --version

# Check required extensions
php -m | grep -E "(fileinfo|mbstring|json|openssl)"

# Check Livewire version
composer show livewire/livewire
```

## Next Steps

- [Installation Overview](./installation.md) - Install the package
- [Configuration](./configuration.md) - Configure package settings
