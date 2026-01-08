---
title: Installation Overview
---

# Installation

This section covers everything you need to get ArtisanPack UI Forms installed and configured in your Laravel application.

## Installation Topics

- [Installation Overview](./installation/installation.md) - Step-by-step installation guide
- [Requirements](./installation/requirements.md) - System requirements and dependencies
- [Configuration](./installation/configuration.md) - Configuration options and customization

## Quick Install

```bash
# Install via Composer
composer require artisanpack-ui/forms

# Publish assets
php artisan vendor:publish --provider="ArtisanPackUI\Forms\FormsServiceProvider"

# Run migrations
php artisan migrate
```

For detailed installation instructions, see the [Installation Overview](./installation/installation.md).
