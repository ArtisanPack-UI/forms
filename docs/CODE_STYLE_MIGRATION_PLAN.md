# Code Style Migration Plan for Forms Package

This document outlines the plan to migrate the `artisanpack-ui/forms` package from the default Laravel Pint code style to the ArtisanPack UI code style defined in `artisanpack-ui/code-style` (PHPCS) and `artisanpack-ui/code-style-pint` (Pint) packages.

## Current State

The forms package currently:
- Has `artisanpack-ui/code-style` and `artisanpack-ui/code-style-pint` listed in `require-dev`
- Does **not** have a `pint.json` configuration file
- Does **not** have a `phpcs.xml` configuration file
- Uses the default Laravel Pint preset when running `./vendor/bin/pint`

## Goal

Configure the forms package to use the ArtisanPack UI code style, which includes:
- **Pint** (auto-formatting): 54 rules covering formatting, code structure, and best practices
- **PHPCS** (validation): 16+ custom sniffs for additional style enforcement and security checks

## Implementation Steps

### Step 1: Install PHP-CS-Fixer

Add PHP-CS-Fixer as a dev dependency (in addition to existing code-style packages):

```bash
composer require --dev friendsofphp/php-cs-fixer
```

### Step 2: Create `.php-cs-fixer.dist.php` Configuration

Copy the WordPress-style spacing configuration from the code-style-pint package:

```bash
cp vendor/artisanpack-ui/code-style-pint/stubs/.php-cs-fixer.dist.php.stub .php-cs-fixer.dist.php
```

Then modify to set `declare_strict_types` to `false`.

### Step 3: Create `phpcs.xml` Configuration

Create a `phpcs.xml` file at the package root:

```xml
<?xml version="1.0"?>
<ruleset name="ArtisanPackUI Forms">
    <description>ArtisanPack UI coding standard for the Forms package</description>

    <!-- Use the ArtisanPackUI standard -->
    <rule ref="ArtisanPackUIStandard"/>

    <!-- Paths to check -->
    <file>src</file>
    <file>routes</file>
    <file>tests</file>

    <!-- Exclude paths -->
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>
    <exclude-pattern>*/database/migrations/*</exclude-pattern>
    <exclude-pattern>*/resources/views/*</exclude-pattern>
</ruleset>
```

### Step 4: Update `composer.json` Scripts

Add convenience scripts for running code style checks:

```json
{
    "scripts": {
        "lint": [
            "./vendor/bin/php-cs-fixer fix --dry-run --diff",
            "./vendor/bin/phpcs"
        ],
        "fix": "./vendor/bin/php-cs-fixer fix",
        "cs": "./vendor/bin/phpcs",
        "cs:fix": "./vendor/bin/phpcbf"
    }
}
```

### Step 5: Run Initial Code Formatting

Execute PHP-CS-Fixer to auto-fix the majority of code style issues:

```bash
./vendor/bin/php-cs-fixer fix
```

This will automatically fix ~70% of code style issues including:
- Indentation and spacing (with WordPress-style parentheses)
- Import ordering (alphabetical)
- Array syntax (`[]` instead of `array()`)
- Trailing commas in multiline constructs
- Binary operator alignment
- Single quotes for strings
- Yoda conditions
- Spaces inside parentheses (`if ( $var )`)

### Step 6: Run PHPCS and Address Remaining Issues

After PHP-CS-Fixer formatting, run PHPCS to identify remaining issues:

```bash
./vendor/bin/phpcs
```

PHPCS will catch issues that PHP-CS-Fixer cannot auto-fix:
- Missing return type declarations
- Missing parameter type hints
- Security issues (unescaped output, unsanitized input)
- Naming convention violations
- Line length violations (if any)

Manually fix any reported issues.

### Step 7: Run Tests

Verify all tests still pass after code style changes:

```bash
./vendor/bin/pest
```

### Step 8: Add GitLab CI Configuration

Add a lint job to `.gitlab-ci.yml`:

```yaml
lint:
  stage: test
  script:
    - composer install --no-interaction --prefer-dist
    - ./vendor/bin/php-cs-fixer fix --dry-run --diff
    - ./vendor/bin/phpcs
  only:
    - merge_requests
    - main
```

### Step 9: Commit Changes

Commit all changes with a clear commit message:

```
Apply ArtisanPack UI code style

- Add .php-cs-fixer.dist.php with WordPress-style spacing
- Add phpcs.xml configuration
- Update composer.json with lint scripts
- Add lint job to .gitlab-ci.yml
- Apply code formatting with PHP-CS-Fixer
- Fix remaining PHPCS issues
```

## Key Code Style Rules

### Formatting (PHP-CS-Fixer - Auto-fixable)

| Rule | Description |
|------|-------------|
| `array_syntax` | Use short array syntax `[]` |
| `binary_operator_spaces` | Align `=` and `=>` operators |
| `single_quote` | Use single quotes for strings |
| `trailing_comma_in_multiline` | Add trailing commas |
| `ordered_imports` | Alphabetically sort imports |
| `concat_space` | Spaces around `.` concatenation |
| WordPress spacing fixers | Spaces inside parentheses `if ( $var )` |

### Best Practices (PHP-CS-Fixer)

| Rule | Description |
|------|-------------|
| `declare_strict_types` | **Disabled** for this package |
| `void_return` | Explicit void return types |
| `yoda_style` | `true === $var` comparisons |
| `visibility_required` | Explicit visibility on all members |

### Validation (PHPCS - Manual fixes may be required)

| Sniff | Description |
|-------|-------------|
| `TypeDeclaration` | Require return type declarations |
| `EscapeOutput` | Validate output escaping in views |
| `ValidatedSanitizedInput` | Check input sanitization |
| `NamingConventions` | Enforce naming standards |
| `DisallowedFunctions` | Block `die()`, `var_dump()`, etc. |

## Excluded Paths

The following paths are excluded from code style checks:
- `vendor/`
- `node_modules/`
- `database/migrations/`
- `resources/views/`

## Expected Changes

Based on the ArtisanPack UI code style, expect the following types of changes:

1. **Import statements** will be reordered alphabetically
2. **Arrays** will use short syntax with trailing commas
3. **String concatenation** will have spaces around the `.` operator
4. **Comparisons** will use Yoda style (`'value' === $var`)
5. **Methods** will have explicit return type declarations
6. **Strict types** declaration will be added to all PHP files (if enabled)
7. **Visibility** will be explicitly declared on all properties and methods

## Timeline

| Step | Description | Estimated Effort |
|------|-------------|------------------|
| 1 | Create pint.json | 5 minutes |
| 2 | Create phpcs.xml | 5 minutes |
| 3 | Update composer.json | 5 minutes |
| 4 | Run Pint formatting | 2 minutes |
| 5 | Fix PHPCS issues | 30-60 minutes (depends on issue count) |
| 6 | Run tests | 5 minutes |
| 7 | Commit | 5 minutes |

## Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| Tests fail after formatting | Run tests frequently during the process |
| Large number of PHPCS errors | Address in batches, prioritize critical issues |
| Breaking changes in public API | Code style changes should not affect behavior |

## Decisions Made

1. **Strict Types**: `false`
   - Consistent with `livewire-ui-components` package
   - Forms packages deal with varied user input types
   - Less disruptive to existing codebase

2. **WordPress-Style Spacing**: Yes - spaces inside parentheses (`if ( $var )`)
   - Using PHP-CS-Fixer instead of Pint for this capability
   - Requires `.php-cs-fixer.dist.php` configuration file

3. **CI Integration**: Yes - GitLab CI
   - Will add lint job to existing `.gitlab-ci.yml` file

## Approval

- [x] Plan reviewed and approved
- [x] Decision made on strict types: `false`
- [x] Decision made on spacing style: WordPress-style with PHP-CS-Fixer
- [x] CI platform decided: GitLab CI
- [x] Ready to proceed with implementation
- [x] **IMPLEMENTED** on January 8, 2026

## Implementation Notes

The migration was completed with the following adjustments:

1. **PHP-CS-Fixer**: Applied formatting to 100 files with WordPress-style spacing
2. **PHPCS Configuration**: Many sniffs were disabled as they:
   - Conflict with PHP-CS-Fixer's formatting (alignment, spacing)
   - Produce false positives with Eloquent (security sniffs flagging `$this->property`)
3. **Active PHPCS Sniffs**: DisallowedFunctions, PhpTags (minimal but effective)
4. **Tests**: All 343 tests pass after code style changes
