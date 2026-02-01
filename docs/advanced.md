---
title: Advanced Topics Overview
---

# Advanced Topics

Advanced configuration and integration options for ArtisanPack UI Forms.

## Advanced Topics

- [Advanced Overview](Advanced-Advanced) - Introduction to advanced features
- [Webhooks](Advanced-Webhooks) - Send data to external services
- [Spam Protection](Advanced-Spam-Protection) - Protect forms from spam
- [Customization](Advanced-Customization) - Extend and customize the package
- [Artisan Commands](Advanced-Artisan-Commands) - CLI commands

## Quick Reference

### Enable Webhooks

```php
// .env
FORMS_WEBHOOKS_ENABLED=true
FORMS_WEBHOOK_URL=https://example.com/webhook
FORMS_WEBHOOK_SECRET=your-secret-key
```

### Configure Spam Protection

```php
// config/artisanpack/forms.php
'spam_protection' => [
    'honeypot' => ['enabled' => true],
    'rate_limit' => ['enabled' => true, 'attempts' => 5],
],
```

### Prune Old Submissions

```bash
php artisan forms:prune-submissions --days=365
```

For detailed documentation, see [Advanced Overview](Advanced-Advanced).
