---
title: Advanced Topics Overview
---

# Advanced Topics

Advanced configuration and integration options for ArtisanPack UI Forms.

## Advanced Topics

- [Advanced Overview](./advanced/advanced.md) - Introduction to advanced features
- [Webhooks](./advanced/webhooks.md) - Send data to external services
- [Spam Protection](./advanced/spam-protection.md) - Protect forms from spam
- [Customization](./advanced/customization.md) - Extend and customize the package
- [Artisan Commands](./advanced/artisan-commands.md) - CLI commands

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

For detailed documentation, see [Advanced Overview](./advanced/advanced.md).
