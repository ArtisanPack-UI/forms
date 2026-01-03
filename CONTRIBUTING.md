# Contributing to ArtisanPack UI

As an open source project, ArtisanPack UI is open to contributions from everyone. You don't need to be a developer to contribute. Whether it's contributing code, writing documentation, testing the CMS or anything in between there's a place for you here to contribute.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Ways to Contribute](#ways-to-contribute)
- [Getting Started](#getting-started)
- [Issue Templates](#issue-templates)
- [Branching Strategy](#branching-strategy)
- [Merge Request Process](#merge-request-process)
- [Label System](#label-system)
- [Milestone Strategy](#milestone-strategy)
- [Forking and Contributing](#forking-and-contributing)
- [Naming Conventions](#naming-conventions)

## Code of Conduct

In order to make this a best place for everyone to contribute, there are some hard and fast rules that everyone needs to abide by.

* ArtisanPack UI is open to everyone no matter your race, ethnicity, gender, who you love, etc. In order to keep it that way, there's zero tolerance for any racist, misogynistic, xenophobic, bigoted, Zionist, antisemitic (yes, there is a difference), Islamophobic, etc. messages. This includes messages sent to a fellow contributor outside of this repository. In short, don't be a jerk. Failure to comply will result in a ban from the project.
* Be respectful when communicating with fellow contributors.
* Respect the decisions made for what to include in the CMS.
* Work together to create the best possible content management system.

## Ways to Contribute

There are a ton of different ways to contribute to ArtisanPack UI even if you're not a developer. Here are some (but not all) of the ways you can contribute to the project:

* Write code for ArtisanPack UI core
* Create plugins to extend ArtisanPack UI
* Create themes to add designs for ArtisanPack UI
* Test and report bugs found in the CMS
* Write documentation
* Write tutorials and talk about ArtisanPack UI on your blog and/or social media profiles
* Review pull/merge requests
* Improve existing code
* Help answer questions in issues

## Getting Started

### Prerequisites

Before contributing, make sure you have:
- Git installed on your machine
- PHP 8.1 or higher
- Composer
- A GitLab, GitHub, or other Git hosting account

### Setting Up Your Development Environment

1. Fork the repository (see [Forking and Contributing](#forking-and-contributing))
2. Clone your fork locally
3. Install dependencies: `composer install`
4. Create a feature branch: `git checkout -b feature/your-feature-name`
5. Make your changes
6. Test your changes
7. Push to your fork
8. Create a merge/pull request

## Issue Templates

When creating an issue, you'll be prompted to choose a template. We have several templates to help you provide the right information:

### Bug Report Template

Use this template when you've found a bug. It will ask for:
- **Expected behavior** - What should happen
- **Current behavior** - What actually happens
- **Steps to reproduce** - How to recreate the bug
- **Environment** - Your OS, browser, PHP version, project version
- **Screenshots** - If applicable

The template automatically applies these labels:
- `Type::Bug`
- `Status::Backlog`

**You should also add:**
- `Priority::*` (Critical, High, Medium, or Low) if urgent
- `Area::*` (Frontend, Backend, etc.) for the affected area

### Feature Request Template

Use this when suggesting new functionality. It will ask for:
- **Problem statement** - What problem does this solve?
- **Proposed solution** - What would you like to happen?
- **Alternatives considered** - Other solutions you've thought about
- **Use cases** - How would this be used?

The template automatically applies:
- `Type::Feature`
- `Status::Backlog`

### Enhancement Template

Use this for improvements to existing features. It will ask for:
- **Current behavior** - How it works now
- **Proposed improvement** - How to make it better
- **Benefits** - Why this improvement is valuable
- **Backwards compatibility** - Will this break anything?

The template automatically applies:
- `Type::Enhancement`
- `Status::Backlog`

### Task Template

Use this for general tasks that don't fit other categories. It will ask for:
- **Task description** - What needs to be done
- **Acceptance criteria** - How we know it's complete
- **Context** - Why this is needed

The template automatically applies:
- `Status::Backlog`

### Submitting Your Issue

After filling out the template:
1. Review your issue for completeness
2. The labels will be applied automatically
3. Add any additional labels if needed (Priority, Area)
4. Submit the issue
5. A maintainer will review and triage it

**Note:** Issues are initially added to the "Future Release" milestone until they're scheduled for a specific version.

## Branching Strategy

We use GitLab Flow with release branches. Here's how it works:

### Main Branches

- **`main`** - Latest stable release
  - All releases are tagged from main
  - Protected: No direct pushes allowed
  
- **`release/X.Y.x`** - Long-term support branches for patch releases
  - Example: `release/1.0.x` for v1.0.1, v1.0.2, etc.
  - Created when needed for patches

### Feature Branches

When contributing, create a feature branch:

**Format:** `feature/short-description` or `fix/short-description`

**Examples:**
- `feature/add-dark-mode`
- `fix/navigation-bug`
- `feature/issue-123-user-profiles`

### Creating Your Branch

```bash
# For new features
git checkout main
git pull origin main
git checkout -b feature/your-feature

# For bug fixes
git checkout main
git pull origin main
git checkout -b fix/your-bugfix
```

### Workflow

1. **Create branch** from `main`
2. **Make changes** and commit
3. **Push** to your fork
4. **Create MR** to `main` branch
5. **Wait for review** from maintainer
6. **Address feedback** if needed
7. **Maintainer merges** when approved

**Important:** Always create your branch from `main` and target `main` in your merge request.

## Merge Request Process

### Before Creating a Merge Request

1. **Ensure there isn't an existing MR** for the same change
2. **Create or link to an issue** - All MRs should reference an issue
3. **Test your changes** locally
4. **Run code linting** - Follow the naming conventions
5. **Update documentation** if needed

### Creating Your Merge Request

We have templates for different types of merge requests:

#### Default Template (Bug Fixes, Features, Enhancements, Tasks)

Use this for most MRs. It includes:
- Description of changes
- Type of change (Bug fix, Feature, Enhancement, etc.)
- Testing performed
- **Accessibility tests** (required for all UI changes)
- Tests added
- Documentation updates
- Pre-submission checklist

The template automatically applies:
- `Status::In Review`

**You should also add:**
- `Type::*` (Bug, Feature, Enhancement, etc.)
- `Area::*` (Frontend, Backend, etc.)

#### Release Template (Maintainers Only)

This template is for release merge requests and should only be used by maintainers.

### Merge Request Guidelines

**For External Contributors:**
1. Create your MR using the Default template
2. Fill out all sections completely
3. Link to the related issue: `Closes #123`
4. Wait for maintainer review
5. Address any feedback promptly
6. A maintainer will approve and merge your MR

**Note:** All MRs require maintainer approval. External contributors cannot merge their own MRs.

### Code Review Process

When you submit an MR:
1. A maintainer will review within 1-3 days
2. They may request changes or ask questions
3. Address feedback by pushing new commits
4. Once approved, the maintainer will merge
5. Your branch will be automatically deleted

### After Your MR is Merged

- Your changes will be included in the next release
- The related issue will automatically close
- You'll be credited in the release notes
- Thank you for contributing! 🎉

## Label System

We use a comprehensive label system to organize issues and merge requests:

### Status Labels (Workflow)

Labels that track where an issue/MR is in the workflow:
- `Status::Backlog` - Not yet prioritized
- `Status::To Do` - Ready to work on
- `Status::In Progress` - Currently being worked on
- `Status::In Review` - Under code review
- `Status::Approved` - Approved and ready to merge
- `Status::Blocked` - Cannot proceed (explain in comments)
- `Status::On Hold` - Paused temporarily

### Type Labels (What It Is)

Labels that categorize the work:
- `Type::Bug` - Something isn't working
- `Type::Feature` - New functionality
- `Type::Enhancement` - Improvement to existing feature
- `Type::Documentation` - Documentation updates
- `Type::Refactor` - Code improvement without behavior change
- `Type::Security` - Security-related changes
- `Type::Performance` - Performance improvements
- `Type::Experimental` - Experimental features

### Priority Labels (Urgency)

Labels that indicate importance:
- `Priority::Critical` - Broken functionality, needs immediate fix
- `Priority::High` - Important, should be addressed soon
- `Priority::Medium` - Normal priority
- `Priority::Low` - Nice to have, low urgency

### Area Labels (Where)

Labels that indicate affected code area:
- `Area::Frontend` - UI/client-side code
- `Area::Backend` - Server/API code
- `Area::Design` - Visual design work
- `Area::Infrastructure` - DevOps/deployment
- `Area::Testing` - Test-related work

### Special Labels

- `good first issue` - Good for new contributors
- `help wanted` - Community assistance requested
- `breaking change` - Breaks backward compatibility
- `accessibility` - Accessibility improvements

**Templates apply some labels automatically, but you may need to add others manually.**

## Milestone Strategy

We use milestones to organize and schedule work:

### How Milestones Work

- **Current Version** (e.g., `v1.0`) - Actively being developed
- **Version Planning** (e.g., `v1.x`) - Planned for future v1 releases
- **Future Release** - Nice-to-have features, no timeline yet

### For Contributors

When you create an issue:
- It's initially unassigned to a milestone
- A maintainer will assign it to a milestone during triage
- `Future Release` = under consideration but not scheduled
- `v1.x` or `v2.x` = planned for that major version
- `v1.0`, `v1.1`, etc. = scheduled for that specific release

**You don't need to assign milestones** - maintainers will handle this.

### For Maintainers

- Assign issues to specific versions when scheduled
- Use `vX.x` for planned but not yet scheduled features
- Use `Future Release` for community requests
- Create patch milestones (v1.0.1) only when needed

## Forking and Contributing

ArtisanPack UI is primarily hosted on GitLab, but you can contribute from any Git hosting platform.

### From GitLab (Primary)

**Easiest method:**

1. **Fork the repository**
   - Go to the project page
   - Click "Fork" button
   - Fork will be created in your account

2. **Clone your fork**
   ```bash
   git clone git@gitlab.com:your-username/artisanpack-ui-package.git
   cd artisanpack-ui-package
   ```

3. **Add upstream remote**
   ```bash
   git remote add upstream git@gitlab.com:jacob-martella-web-design/artisanpack-ui/package-name.git
   ```

4. **Create feature branch**
   ```bash
   git checkout -b feature/your-feature
   ```

5. **Make changes and push**
   ```bash
   git add .
   git commit -m "Add your feature"
   git push origin feature/your-feature
   ```

6. **Create Merge Request**
   - Go to your fork on GitLab
   - Click "Create merge request"
   - Target the original repository's `main` branch
   - Fill out the MR template
   - Submit

### From GitHub

**If you prefer GitHub:**

1. **Clone on GitLab** (even without account)
   ```bash
   git clone https://gitlab.com/jacob-martella-web-design/artisanpack-ui/package-name.git
   cd package-name
   ```

2. **Create repository on GitHub**
   - Go to GitHub and create a new repository
   - Don't initialize with README

3. **Add GitHub as remote**
   ```bash
   git remote add github git@github.com:your-username/package-name.git
   ```

4. **Create feature branch**
   ```bash
   git checkout -b feature/your-feature
   ```

5. **Make changes and push to GitHub**
   ```bash
   git add .
   git commit -m "Add your feature"
   git push github feature/your-feature
   ```

6. **Create Pull Request**
   - Create PR on GitHub as normal
   - Mention you're contributing to a GitLab project
   - Include: "This PR is for GitLab project: [link]"

7. **Maintainer will create GitLab MR**
   - Maintainer will pull your changes
   - Create MR on GitLab
   - Credit you in commits

**Note:** This requires maintainer coordination. GitLab forks are preferred.

### From Bitbucket

Similar to GitHub process:

1. **Clone from GitLab**
   ```bash
   git clone https://gitlab.com/jacob-martella-web-design/artisanpack-ui/package-name.git
   cd package-name
   ```

2. **Create Bitbucket repository**

3. **Add Bitbucket remote**
   ```bash
   git remote add bitbucket git@bitbucket.org:your-username/package-name.git
   ```

4. **Push to Bitbucket**
   ```bash
   git checkout -b feature/your-feature
   # ... make changes ...
   git push bitbucket feature/your-feature
   ```

5. **Notify maintainer**
   - Create issue on GitLab: "Contribution available"
   - Link to your Bitbucket branch
   - Maintainer will integrate

### From Local Git (No Account)

**If you don't want any hosting account:**

1. **Clone project**
   ```bash
   git clone https://gitlab.com/jacob-martella-web-design/artisanpack-ui/package-name.git
   cd package-name
   ```

2. **Create feature branch**
   ```bash
   git checkout -b feature/your-feature
   ```

3. **Make changes**
   ```bash
   # ... work on your feature ...
   git add .
   git commit -m "Add your feature"
   ```

4. **Create patch file**
   ```bash
   git format-patch main --stdout > my-contribution.patch
   ```

5. **Submit patch**
   - Create GitLab issue (no account needed via email)
   - Or email patch to: [your email or link to contribution email]
   - Describe changes in issue/email
   - Attach `.patch` file

6. **Maintainer applies patch**
   ```bash
   git apply my-contribution.patch
   ```

### Keeping Your Fork Updated

**For GitLab forks:**

```bash
# Fetch upstream changes
git fetch upstream

# Merge into your main
git checkout main
git merge upstream/main

# Push to your fork
git push origin main
```

**For other platforms:**

```bash
# Add GitLab as upstream
git remote add upstream https://gitlab.com/jacob-martella-web-design/artisanpack-ui/package-name.git

# Fetch and merge
git fetch upstream
git checkout main
git merge upstream/main

# Push to your platform
git push origin main  # or 'github' or 'bitbucket'
```

### Contribution Workflow Summary

| Platform | Difficulty | Preferred? | Notes |
|----------|-----------|------------|-------|
| GitLab Fork | ⭐ Easy | ✅ Yes | Native workflow, use this if possible |
| GitHub | ⭐⭐ Medium | ⚠️ Okay | Requires maintainer coordination |
| Bitbucket | ⭐⭐ Medium | ⚠️ Okay | Requires maintainer coordination |
| Local/Patch | ⭐⭐⭐ Advanced | ⚠️ Last resort | For privacy or no-account contributors |

**Recommendation:** Use GitLab fork whenever possible for the smoothest contribution experience.

## Naming Conventions

To keep things consistent across the code base, it's important to follow these naming conventions:

### PHP Code

- **Class names**: Pascal Case - `ClassName`
- **Function names**: Camel Case - `functionName`
- **Variables**: Camel Case - `variableName`
- **Array keys**: Camel Case - `$array['arrayKey']`
- **Database columns**: Snake case - `table_column`
- **Constants**: Upper snake case - `CONSTANT_NAME`

### Files and Directories

- **PHP class files**: Match class name - `ClassName.php`
- **Config files**: Kebab case - `config-name.php`
- **View files**: Kebab case - `view-name.blade.php`

### Git Branches

- **Feature branches**: `feature/short-description`
- **Bug fix branches**: `fix/short-description`
- **Use hyphens** not underscores
- **Keep it short** but descriptive
- **Examples**: `feature/dark-mode`, `fix/navbar-responsive`

### Commit Messages

Follow conventional commit format:

```
type: Short description

Longer description if needed.

Closes #123
```

**Types:**
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting)
- `refactor:` - Code refactoring
- `test:` - Test updates
- `chore:` - Maintenance tasks

**Examples:**
```
feat: Add dark mode support

Implements dark mode theme with toggle in settings.
Includes proper color contrast for accessibility.

Closes #456
```

```
fix: Resolve navigation menu overlap on mobile

Menu was overlapping content on screens < 768px.
Updated CSS media queries and z-index values.

Closes #789
```

## Questions?

If you have questions about contributing:

1. **Check existing documentation** - Wiki, README, this guide
2. **Search existing issues** - Your question might be answered
3. **Ask in an issue** - Create a question issue
4. **Join discussions** - Comment on relevant issues

## Thank You!

Thank you for contributing to ArtisanPack UI! Your contributions help make this project better for everyone.

Every contribution matters, whether it's:
- 🐛 Fixing a typo in documentation
- ✨ Adding a major feature
- 🧪 Writing tests
- 📝 Improving documentation
- 💡 Suggesting ideas

We appreciate your time and effort! 🎉

---

**Project Maintainer:** Jacob Martella ([@viewfromthebox94](https://gitlab.com/viewfromthebox94))  
**License:** [MIT](LICENSE)
**Website:** [https://jacobmartella.me](https://jacobmartella.me)
